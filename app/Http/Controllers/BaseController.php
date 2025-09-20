<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $tenant;
    protected $perPage = 15;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->setTenant($request);
            return $next($request);
        });
    }

    /**
     * Set the current tenant based on request
     */
    protected function setTenant(Request $request)
    {
        // Get tenant from various sources (subdomain, header, etc.)
        $tenantId = $this->resolveTenantId($request);
        
        if ($tenantId) {
            $this->tenant = Tenant::find($tenantId);
            
            if (!$this->tenant || !$this->tenant->isActive()) {
                abort(403, 'Tenant not found or inactive');
            }
        }
    }

    /**
     * Resolve tenant ID from request
     */
    protected function resolveTenantId(Request $request): ?int
    {
        // Priority: 1. Header, 2. User's tenant, 3. Subdomain
        
        // From X-Tenant-ID header
        if ($request->hasHeader('X-Tenant-ID')) {
            return (int) $request->header('X-Tenant-ID');
        }

        // From authenticated user
        if (auth()->check() && auth()->user()->tenant_id) {
            return auth()->user()->tenant_id;
        }

        // From subdomain (if using subdomain-based multi-tenancy)
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        if ($subdomain && $subdomain !== 'www') {
            $tenant = Tenant::where('tenant_code', $subdomain)->first();
            return $tenant?->tenant_id;
        }

        return null;
    }

    /**
     * Get current tenant
     */
    protected function getCurrentTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Ensure tenant is set
     */
    protected function requireTenant()
    {
        if (!$this->tenant) {
            abort(400, 'Tenant context required');
        }
    }

    /**
     * Apply tenant scope to query
     */
    protected function applyTenantScope($query)
    {
        $this->requireTenant();
        return $query->where('tenant_id', $this->tenant->tenant_id);
    }

    /**
     * Success response helper
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->tenant_id,
                'name' => $this->tenant->company_name,
                'code' => $this->tenant->tenant_code
            ] : null
        ], $code);
    }

    /**
     * Error response helper
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->tenant_id,
                'name' => $this->tenant->company_name,
                'code' => $this->tenant->tenant_code
            ] : null
        ], $code);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse($validator): JsonResponse
    {
        return $this->errorResponse(
            'Validation failed',
            422,
            $validator->errors()
        );
    }

    /**
     * Paginated response helper
     */
    protected function paginatedResponse($query, Request $request, string $message = 'Data retrieved successfully')
    {
        $perPage = $request->get('per_page', $this->perPage);
        $perPage = min($perPage, 100); // Max 100 items per page
        
        $data = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more_pages' => $data->hasMorePages()
            ],
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->tenant_id,
                'name' => $this->tenant->company_name,
                'code' => $this->tenant->tenant_code
            ] : null
        ]);
    }

    /**
     * Check if user has permission
     */
    protected function checkPermission(string $permission): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        
        // Super admin has all permissions
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check if user belongs to current tenant
        if ($this->tenant && $user->tenant_id !== $this->tenant->tenant_id) {
            return false;
        }

        return $user->hasPermission($permission);
    }

    /**
     * Require permission or abort
     */
    protected function requirePermission(string $permission)
    {
        if (!$this->checkPermission($permission)) {
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * Check if user can access resource
     */
    protected function canAccessResource($resource): bool
    {
        if (!$this->tenant) {
            return false;
        }

        // Check if resource belongs to current tenant
        if (method_exists($resource, 'tenant_id')) {
            return $resource->tenant_id === $this->tenant->tenant_id;
        }

        return true;
    }

    /**
     * Log activity for audit trail
     */
    protected function logActivity(string $action, string $tableName = null, int $recordId = null, array $data = [])
    {
        if (!$this->tenant) {
            return;
        }

        \App\Models\AuditLog::logAction(
            $this->tenant->tenant_id,
            $action,
            $tableName,
            $recordId,
            $data
        );
    }

    /**
     * Handle common search and filter parameters
     */
    protected function applyFilters($query, Request $request, array $searchFields = [])
    {
        // Search
        if ($request->has('search') && !empty($searchFields)) {
            $search = $request->get('search');
            $query->where(function ($q) use ($searchFields, $search) {
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$search}%");
                }
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Active/inactive filter
        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        if (in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        return $query;
    }

    /**
     * Validate tenant limits
     */
    protected function checkTenantLimits(string $resource, int $currentCount = null): bool
    {
        if (!$this->tenant) {
            return false;
        }

        $limits = [
            'users' => $this->tenant->max_users,
            'products' => $this->tenant->max_products,
            'warehouses' => $this->tenant->max_warehouses,
            'shops' => $this->tenant->max_shops
        ];

        if (!isset($limits[$resource])) {
            return true; // No limit defined
        }

        $limit = $limits[$resource];
        if ($limit === null || $limit === 0) {
            return true; // Unlimited
        }

        if ($currentCount === null) {
            // Get current count based on resource type
            $currentCount = match($resource) {
                'users' => $this->tenant->users()->count(),
                'products' => $this->tenant->products()->count(),
                'warehouses' => $this->tenant->warehouses()->count(),
                'shops' => $this->tenant->shops()->count(),
                default => 0
            };
        }

        return $currentCount < $limit;
    }

    /**
     * Generate unique code for tenant resources
     */
    protected function generateUniqueCode(string $prefix, $model, string $field = 'code'): string
    {
        $this->requireTenant();
        
        do {
            $code = $prefix . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $model::where('tenant_id', $this->tenant->tenant_id)
                ->where($field, $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
