<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function __construct()
    {
        // Don't apply tenant middleware for auth endpoints
        $this->middleware(function ($request, $next) {
            // Only set tenant for protected endpoints
            if (in_array($request->route()->getName(), ['auth.profile', 'auth.logout', 'auth.change-password'])) {
                $this->setTenant($request);
            }
            return $next($request);
        });
    }

    /**
     * User login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'tenant_code' => 'nullable|string|max:20',
            'remember_me' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            // Find user by email
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // Check if user is active
            if (!$user->isActive()) {
                return $this->errorResponse('Account is deactivated', 403);
            }

            // Get tenant
            $tenant = null;
            if ($request->tenant_code) {
                $tenant = Tenant::where('tenant_code', $request->tenant_code)->first();
                if (!$tenant || $tenant->tenant_id !== $user->tenant_id) {
                    return $this->errorResponse('Invalid tenant or user does not belong to this tenant', 403);
                }
            } else {
                $tenant = $user->tenant;
            }

            if (!$tenant || !$tenant->isActive()) {
                return $this->errorResponse('Tenant not found or inactive', 403);
            }

            // Generate session token
            $sessionToken = Str::random(60);

            // Create user session
            $session = UserSession::createSession(
                $tenant->tenant_id,
                $user->user_id,
                $sessionToken,
                $request->ip(),
                $request->userAgent()
            );

            // Update user last login
            $user->updateLastLogin();

            // Log activity
            \App\Models\AuditLog::logAction(
                $tenant->tenant_id,
                'login',
                'users',
                $user->user_id,
                ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]
            );

            DB::commit();

            // Prepare response data
            $responseData = [
                'user' => [
                    'id' => $user->user_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->getPermissions(),
                    'warehouse_id' => $user->warehouse_id,
                    'shop_id' => $user->shop_id,
                    'profile_image_url' => $user->profile_image_url
                ],
                'tenant' => [
                    'id' => $tenant->tenant_id,
                    'name' => $tenant->company_name,
                    'code' => $tenant->tenant_code,
                    'logo_url' => $tenant->logo_url,
                    'primary_color' => $tenant->primary_color,
                    'secondary_color' => $tenant->secondary_color,
                    'subscription_plan' => $tenant->subscription_plan,
                    'is_trial' => $tenant->is_trial,
                    'trial_days_remaining' => $tenant->getRemainingTrialDays()
                ],
                'session' => [
                    'token' => $sessionToken,
                    'expires_in' => 60 * 60 * 24 * 7, // 7 days in seconds
                    'session_id' => $session->session_id
                ]
            ];

            return $this->successResponse($responseData, 'Login successful');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * User logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $sessionToken = $request->bearerToken() ?? $request->header('X-Session-Token');
            
            if ($sessionToken) {
                $session = UserSession::findByToken($sessionToken);
                if ($session) {
                    $session->terminate();
                    
                    // Log activity
                    if ($this->tenant) {
                        \App\Models\AuditLog::logAction(
                            $this->tenant->tenant_id,
                            'logout',
                            'users',
                            $session->user_id
                        );
                    }
                }
            }

            return $this->successResponse(null, 'Logout successful');

        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user profile
     */
    public function profile(): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $user = auth()->user();
        $user->load(['warehouse', 'shop', 'customerProfile']);

        $profileData = [
            'user' => $user,
            'tenant' => [
                'id' => $this->tenant->tenant_id,
                'name' => $this->tenant->company_name,
                'code' => $this->tenant->tenant_code,
                'logo_url' => $this->tenant->logo_url,
                'primary_color' => $this->tenant->primary_color,
                'secondary_color' => $this->tenant->secondary_color
            ],
            'permissions' => $user->getPermissions(),
            'recent_activity' => \App\Models\AuditLog::where('user_id', $user->user_id)
                ->latest()
                ->limit(10)
                ->get()
        ];

        return $this->successResponse($profileData, 'Profile retrieved successfully');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:150',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'emergency_contact' => 'nullable|string|max:20',
            'profile_image_url' => 'nullable|url|max:500'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $user->toArray();
            $user->update($validator->validated());

            // Log activity
            \App\Models\AuditLog::logUpdate(
                $this->tenant->tenant_id,
                'users',
                $user->user_id,
                $oldValues,
                $user->toArray()
            );

            DB::commit();

            return $this->successResponse($user, 'Profile updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', 400);
        }

        try {
            DB::beginTransaction();

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Terminate all other sessions for security
            UserSession::terminateAllForUser($user->user_id);

            // Log activity
            \App\Models\AuditLog::logAction(
                $this->tenant->tenant_id,
                'password_changed',
                'users',
                $user->user_id
            );

            DB::commit();

            return $this->successResponse(null, 'Password changed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refresh session token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken() ?? $request->header('X-Session-Token');
        
        if (!$sessionToken) {
            return $this->errorResponse('Session token required', 400);
        }

        $session = UserSession::findByToken($sessionToken);
        
        if (!$session || !$session->isActive()) {
            return $this->errorResponse('Invalid or expired session', 401);
        }

        try {
            DB::beginTransaction();

            // Generate new token
            $newSessionToken = Str::random(60);
            
            // Update session
            $session->update([
                'session_token' => $newSessionToken,
                'login_at' => now() // Extend session
            ]);

            DB::commit();

            $responseData = [
                'session' => [
                    'token' => $newSessionToken,
                    'expires_in' => 60 * 60 * 24 * 7, // 7 days in seconds
                    'session_id' => $session->session_id
                ]
            ];

            return $this->successResponse($responseData, 'Token refreshed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to refresh token: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'tenant_code' => 'nullable|string|max:20'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Don't reveal if email exists or not for security
            return $this->successResponse(null, 'If the email exists, a password reset link has been sent');
        }

        // Validate tenant if provided
        if ($request->tenant_code) {
            $tenant = Tenant::where('tenant_code', $request->tenant_code)->first();
            if (!$tenant || $tenant->tenant_id !== $user->tenant_id) {
                return $this->successResponse(null, 'If the email exists, a password reset link has been sent');
            }
        }

        try {
            // Generate password reset token
            $resetToken = Str::random(60);
            
            // In a real implementation, you would:
            // 1. Store the reset token in a password_resets table with expiration
            // 2. Send email with reset link
            // 3. Implement reset password endpoint

            // For now, just log the activity
            \App\Models\AuditLog::logAction(
                $user->tenant_id,
                'password_reset_requested',
                'users',
                $user->user_id,
                ['reset_token' => $resetToken]
            );

            return $this->successResponse(null, 'If the email exists, a password reset link has been sent');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process forgot password request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify session
     */
    public function verifySession(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken() ?? $request->header('X-Session-Token');
        
        if (!$sessionToken) {
            return $this->errorResponse('Session token required', 400);
        }

        $session = UserSession::findByToken($sessionToken);
        
        if (!$session || !$session->isActive()) {
            return $this->errorResponse('Invalid or expired session', 401);
        }

        $user = $session->user;
        $tenant = $session->tenant;

        if (!$user || !$user->isActive() || !$tenant || !$tenant->isActive()) {
            return $this->errorResponse('User or tenant inactive', 403);
        }

        $responseData = [
            'valid' => true,
            'user' => [
                'id' => $user->user_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->getPermissions()
            ],
            'tenant' => [
                'id' => $tenant->tenant_id,
                'name' => $tenant->company_name,
                'code' => $tenant->tenant_code
            ],
            'session' => [
                'id' => $session->session_id,
                'duration' => $session->getDurationFormatted(),
                'ip_address' => $session->ip_address
            ]
        ];

        return $this->successResponse($responseData, 'Session is valid');
    }

    /**
     * Get user sessions
     */
    public function sessions(): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $sessions = auth()->user()->userSessions()
            ->orderBy('login_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->session_id,
                    'is_active' => $session->isActive(),
                    'login_at' => $session->login_at,
                    'logout_at' => $session->logout_at,
                    'duration' => $session->getDurationFormatted(),
                    'ip_address' => $session->ip_address,
                    'browser' => $session->getBrowser(),
                    'operating_system' => $session->getOperatingSystem(),
                    'device_type' => $session->getDeviceType()
                ];
            });

        return $this->successResponse($sessions, 'User sessions retrieved successfully');
    }

    /**
     * Terminate specific session
     */
    public function terminateSession(int $sessionId): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $session = auth()->user()->userSessions()->find($sessionId);

        if (!$session) {
            return $this->errorResponse('Session not found', 404);
        }

        $session->terminate();

        return $this->successResponse(null, 'Session terminated successfully');
    }

    /**
     * Terminate all sessions except current
     */
    public function terminateAllSessions(Request $request): JsonResponse
    {
        $this->requireTenant();
        
        if (!auth()->check()) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $currentSessionToken = $request->bearerToken() ?? $request->header('X-Session-Token');
        $currentSession = UserSession::findByToken($currentSessionToken);

        $terminatedCount = auth()->user()->userSessions()
            ->where('session_id', '!=', $currentSession?->session_id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_at' => now()
            ]);

        return $this->successResponse([
            'terminated_sessions' => $terminatedCount
        ], 'All other sessions terminated successfully');
    }
}
