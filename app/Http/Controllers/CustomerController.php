<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CustomerController extends BaseController
{
    /**
     * Display a listing of customers for the current tenant
     */
    public function index(Request $request): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.view');
        $this->requireTenant();

        // Get customers with their profiles
        $query = User::where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->with(['customerProfile', 'orders' => function($q) {
                $q->select('order_id', 'customer_id', 'total_amount', 'status', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->limit(5);
            }]);

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'full_name',
            'email',
            'phone_number'
        ]);

        // Customer tier filter
        if ($request->has('customer_tier')) {
            $query->whereHas('customerProfile', function($q) use ($request) {
                $q->where('customer_tier', $request->get('customer_tier'));
            });
        }

        // Country filter
        if ($request->has('country')) {
            $query->whereHas('customerProfile', function($q) use ($request) {
                $q->where('country', $request->get('country'));
            });
        }

        // Gender filter
        if ($request->has('gender')) {
            $query->whereHas('customerProfile', function($q) use ($request) {
                $q->where('gender', $request->get('gender'));
            });
        }

        // Marketing consent filter
        if ($request->has('marketing_consent')) {
            $query->whereHas('customerProfile', function($q) use ($request) {
                $q->where('marketing_consent', $request->boolean('marketing_consent'));
            });
        }

        // High value customers filter
        if ($request->has('high_value')) {
            $minSpent = $request->get('min_spent', 1000);
            $query->whereHas('customerProfile', function($q) use ($minSpent) {
                $q->where('total_spent', '>=', $minSpent);
            });
        }

        // Date range filter for customer registration
        if ($request->has('registered_from')) {
            $query->whereDate('created_at', '>=', $request->get('registered_from'));
        }

        if ($request->has('registered_to')) {
            $query->whereDate('created_at', '<=', $request->get('registered_to'));
        }

        // Sort by customer tier, total spent, or registration date
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if ($sortBy === 'customer_tier') {
            $query->join('customer_profiles', 'users.user_id', '=', 'customer_profiles.customer_id')
                  ->orderBy('customer_profiles.customer_tier', $sortDirection)
                  ->select('users.*');
        } elseif ($sortBy === 'total_spent') {
            $query->join('customer_profiles', 'users.user_id', '=', 'customer_profiles.customer_id')
                  ->orderBy('customer_profiles.total_spent', $sortDirection)
                  ->select('users.*');
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        return $this->paginatedResponse($query, $request, 'Customers retrieved successfully');
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.create');
        $this->requireTenant();

        // Check tenant limits
        if (!$this->checkTenantLimits('users')) {
            return $this->errorResponse('Customer limit reached for your subscription plan', 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferred_language' => 'nullable|string|max:50',
            'marketing_consent' => 'nullable|boolean',
            'preferred_contact_method' => 'nullable|in:email,phone,sms'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $customerData = $validator->validated();
            $customerData['password'] = Hash::make($customerData['password']);
            $customerData['role'] = 'customer';
            $customerData['tenant_id'] = $this->tenant->tenant_id;
            $customerData['is_active'] = true;

            // Create the customer user
            $customer = User::create($customerData);

            // Create customer profile
            $profileData = [
                'customer_id' => $customer->user_id,
                'tenant_id' => $this->tenant->tenant_id,
                'phone_number' => $customerData['phone_number'] ?? null,
                'address' => $customerData['address'] ?? null,
                'city' => $customerData['city'] ?? null,
                'state' => $customerData['state'] ?? null,
                'postal_code' => $customerData['postal_code'] ?? null,
                'country' => $customerData['country'] ?? null,
                'date_of_birth' => $customerData['date_of_birth'] ?? null,
                'gender' => $customerData['gender'] ?? null,
                'preferred_language' => $customerData['preferred_language'] ?? 'English',
                'marketing_consent' => $customerData['marketing_consent'] ?? false,
                'preferred_contact_method' => $customerData['preferred_contact_method'] ?? 'email',
                'customer_tier' => 'bronze',
                'loyalty_points' => 0,
                'total_orders' => 0,
                'total_spent' => 0
            ];

            $customerProfile = CustomerProfile::create($profileData);

            $this->logActivity('customer_created', 'customers', $customer->user_id);

            DB::commit();

            // Load relationships for response
            $customer->load(['customerProfile']);

            return $this->successResponse($customer, 'Customer created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.view');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->with([
                'customerProfile',
                'orders' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        return $this->successResponse($customer, 'Customer retrieved successfully');
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.update');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string|max:150',
            'email' => 'sometimes|required|email|max:150|unique:users,email,' . $id . ',user_id',
            'password' => 'sometimes|required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'preferred_language' => 'nullable|string|max:50',
            'marketing_consent' => 'nullable|boolean',
            'preferred_contact_method' => 'nullable|in:email,phone,sms',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $updateData = $validator->validated();
            $oldValues = $customer->toArray();

            // Hash password if provided
            if (isset($updateData['password'])) {
                $updateData['password'] = Hash::make($updateData['password']);
            }

            // Update customer user data
            $customer->update($updateData);

            // Update customer profile
            $profileData = array_filter([
                'phone_number' => $updateData['phone_number'] ?? null,
                'address' => $updateData['address'] ?? null,
                'city' => $updateData['city'] ?? null,
                'state' => $updateData['state'] ?? null,
                'postal_code' => $updateData['postal_code'] ?? null,
                'country' => $updateData['country'] ?? null,
                'date_of_birth' => $updateData['date_of_birth'] ?? null,
                'gender' => $updateData['gender'] ?? null,
                'preferred_language' => $updateData['preferred_language'] ?? null,
                'marketing_consent' => $updateData['marketing_consent'] ?? null,
                'preferred_contact_method' => $updateData['preferred_contact_method'] ?? null
            ], function($value) {
                return $value !== null;
            });

            if (!empty($profileData) && $customer->customerProfile) {
                $customer->customerProfile->update($profileData);
            }

            $this->logActivity('customer_updated', 'customers', $customer->user_id, [
                'old_values' => $oldValues,
                'new_values' => $customer->fresh()->toArray()
            ]);

            DB::commit();

            $customer->load(['customerProfile']);

            return $this->successResponse($customer, 'Customer updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.delete');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        // Check if customer has orders
        if ($customer->orders()->exists()) {
            return $this->errorResponse('Cannot delete customer with existing orders. Consider deactivating instead.', 400);
        }

        try {
            DB::beginTransaction();

            // Delete customer profile first
            if ($customer->customerProfile) {
                $customer->customerProfile->delete();
            }

            // Delete customer user
            $customer->delete();

            $this->logActivity('customer_deleted', 'customers', $id);

            DB::commit();

            return $this->successResponse(null, 'Customer deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Deactivate a customer (soft delete alternative)
     */
    public function deactivate(int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.update');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        $customer->deactivate();

        $this->logActivity('customer_deactivated', 'customers', $customer->user_id);

        return $this->successResponse($customer, 'Customer deactivated successfully');
    }

    /**
     * Activate a customer
     */
    public function activate(int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.update');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        $customer->activate();

        $this->logActivity('customer_activated', 'customers', $customer->user_id);

        return $this->successResponse($customer, 'Customer activated successfully');
    }

    /**
     * Get customer statistics
     */
    public function stats(): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.view');
        $this->requireTenant();

        $totalCustomers = User::where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->count();

        $activeCustomers = User::where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('is_active', true)
            ->count();

        $tierStats = CustomerProfile::where('tenant_id', $this->tenant->tenant_id)
            ->selectRaw('customer_tier, COUNT(*) as count')
            ->groupBy('customer_tier')
            ->pluck('count', 'customer_tier')
            ->toArray();

        $totalSpent = CustomerProfile::where('tenant_id', $this->tenant->tenant_id)
            ->sum('total_spent');

        $avgOrderValue = CustomerProfile::where('tenant_id', $this->tenant->tenant_id)
            ->where('total_orders', '>', 0)
            ->avg('total_spent');

        $marketingConsent = CustomerProfile::where('tenant_id', $this->tenant->tenant_id)
            ->where('marketing_consent', true)
            ->count();

        $stats = [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $totalCustomers - $activeCustomers,
            'tier_distribution' => $tierStats,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => round($avgOrderValue, 2),
            'marketing_consent_count' => $marketingConsent,
            'marketing_consent_percentage' => $totalCustomers > 0 ? round(($marketingConsent / $totalCustomers) * 100, 2) : 0
        ];

        return $this->successResponse($stats, 'Customer statistics retrieved successfully');
    }

    /**
     * Get customer orders
     */
    public function orders(int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.view');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->first();

        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        $orders = $customer->orders()
            ->with(['orderItems.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->successResponse($orders, 'Customer orders retrieved successfully');
    }

    /**
     * Update customer loyalty points
     */
    public function updateLoyaltyPoints(Request $request, int $id): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('customers.update');
        $this->requireTenant();

        $customer = User::where('user_id', $id)
            ->where('role', 'customer')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->with('customerProfile')
            ->first();

        if (!$customer || !$customer->customerProfile) {
            return $this->errorResponse('Customer not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:add,redeem,set',
            'points' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $action = $request->get('action');
            $points = $request->get('points');
            $reason = $request->get('reason', 'Manual adjustment');

            $profile = $customer->customerProfile;

            switch ($action) {
                case 'add':
                    $profile->addLoyaltyPoints($points);
                    $message = "Added {$points} loyalty points";
                    break;
                case 'redeem':
                    if (!$profile->redeemLoyaltyPoints($points)) {
                        return $this->errorResponse('Insufficient loyalty points', 400);
                    }
                    $message = "Redeemed {$points} loyalty points";
                    break;
                case 'set':
                    $profile->loyalty_points = $points;
                    $profile->save();
                    $profile->updateTier();
                    $message = "Set loyalty points to {$points}";
                    break;
            }

            $this->logActivity('customer_loyalty_updated', 'customers', $customer->user_id, [
                'action' => $action,
                'points' => $points,
                'reason' => $reason,
                'new_balance' => $profile->fresh()->loyalty_points
            ]);

            DB::commit();

            $customer->load(['customerProfile']);

            return $this->successResponse($customer, $message);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update loyalty points: ' . $e->getMessage(), 500);
        }
    }
}


