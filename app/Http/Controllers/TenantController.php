<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TenantController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        // Only super admins can manage tenants
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
                abort(403, 'Only super administrators can manage tenants');
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of tenants
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        // Apply filters
        $query = $this->applyFilters($query, $request, [
            'company_name',
            'email',
            'tenant_code',
            'contact_person'
        ]);

        // Additional tenant-specific filters
        if ($request->has('subscription_plan')) {
            $query->where('subscription_plan', $request->get('subscription_plan'));
        }

        if ($request->has('business_type')) {
            $query->where('business_type', $request->get('business_type'));
        }

        if ($request->has('is_trial')) {
            $query->where('is_trial', (bool) $request->get('is_trial'));
        }

        if ($request->has('country')) {
            $query->where('country', $request->get('country'));
        }

        return $this->paginatedResponse($query, $request, 'Tenants retrieved successfully');
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_code' => 'required|string|max:20|unique:tenants,tenant_code|alpha_dash',
            'company_name' => 'required|string|max:200',
            'business_type' => 'nullable|string|max:100',
            'subscription_plan' => 'required|in:trial,basic,premium,enterprise',
            'contact_person' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:tenants,email',
            'phone_number' => 'nullable|string|max:20',
            'website_url' => 'nullable|url|max:500',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'company_size' => 'nullable|in:1-10,11-50,51-200,201-1000,1000+',
            'billing_cycle' => 'nullable|in:monthly,quarterly,yearly',
            'currency' => 'nullable|string|max:10',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:10',
            'secondary_color' => 'nullable|string|max:10',
            'custom_domain' => 'nullable|string|max:100|unique:tenants,custom_domain'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $tenantData = $validator->validated();
            
            // Set default values
            $tenantData['subscription_start_date'] = now()->toDateString();
            $tenantData['status'] = 'active';
            $tenantData['is_trial'] = $tenantData['subscription_plan'] === 'trial';
            $tenantData['trial_days_remaining'] = $tenantData['is_trial'] ? 30 : 0;
            
            // Set default limits based on plan
            $limits = $this->getSubscriptionLimits($tenantData['subscription_plan']);
            $tenantData = array_merge($tenantData, $limits);

            $tenant = Tenant::create($tenantData);

            // Create default system configurations for the tenant
            $this->createDefaultConfigurations($tenant);

            // Log the creation
            \App\Models\AuditLog::logAction(
                $tenant->tenant_id,
                'tenant_created',
                'tenants',
                $tenant->tenant_id,
                ['created_by_admin' => auth()->id()]
            );

            DB::commit();

            return $this->successResponse($tenant, 'Tenant created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create tenant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified tenant
     */
    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        // Load additional statistics
        $tenant->load(['users', 'warehouses', 'shops', 'products']);
        
        $stats = [
            'users_count' => $tenant->users()->count(),
            'active_users_count' => $tenant->users()->active()->count(),
            'warehouses_count' => $tenant->warehouses()->count(),
            'shops_count' => $tenant->shops()->count(),
            'products_count' => $tenant->products()->count(),
            'orders_count' => $tenant->orders()->count(),
            'total_revenue' => $tenant->orders()->where('status', 'delivered')->sum('total_amount'),
            'trial_days_remaining' => $tenant->getRemainingTrialDays()
        ];

        $tenant->stats = $stats;

        return $this->successResponse($tenant, 'Tenant retrieved successfully');
    }

    /**
     * Update the specified tenant
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'tenant_code' => 'sometimes|string|max:20|alpha_dash|unique:tenants,tenant_code,' . $id . ',tenant_id',
            'company_name' => 'sometimes|string|max:200',
            'business_type' => 'nullable|string|max:100',
            'subscription_plan' => 'sometimes|in:trial,basic,premium,enterprise',
            'contact_person' => 'sometimes|string|max:150',
            'email' => 'sometimes|email|max:150|unique:tenants,email,' . $id . ',tenant_id',
            'phone_number' => 'nullable|string|max:20',
            'website_url' => 'nullable|url|max:500',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'company_size' => 'nullable|in:1-10,11-50,51-200,201-1000,1000+',
            'status' => 'sometimes|in:active,suspended,cancelled,trial_expired',
            'billing_cycle' => 'nullable|in:monthly,quarterly,yearly',
            'currency' => 'nullable|string|max:10',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:10',
            'secondary_color' => 'nullable|string|max:10',
            'custom_domain' => 'nullable|string|max:100|unique:tenants,custom_domain,' . $id . ',tenant_id',
            'max_users' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'max_warehouses' => 'nullable|integer|min:1',
            'max_shops' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldValues = $tenant->toArray();
            $tenant->update($validator->validated());

            // Update subscription limits if plan changed
            if ($request->has('subscription_plan') && $tenant->wasChanged('subscription_plan')) {
                $limits = $this->getSubscriptionLimits($tenant->subscription_plan);
                $tenant->update($limits);
            }

            // Log the update
            \App\Models\AuditLog::logUpdate(
                $tenant->tenant_id,
                'tenants',
                $tenant->tenant_id,
                $oldValues,
                $tenant->toArray()
            );

            DB::commit();

            return $this->successResponse($tenant, 'Tenant updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update tenant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified tenant
     */
    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        try {
            DB::beginTransaction();

            // Store data for audit log and log BEFORE deletion to satisfy FK
            $tenantData = $tenant->toArray();

            \App\Models\AuditLog::create([
                'tenant_id' => $id,
                'user_id' => auth()->id(),
                'action' => 'tenant_deleted',
                'table_name' => 'tenants',
                'record_id' => $id,
                'old_values' => $tenantData,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Now delete the tenant (cascades will run)
            $tenant->delete();

            DB::commit();

            return $this->successResponse(null, 'Tenant deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete tenant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Suspend tenant
     */
    public function suspend(int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        $tenant->update(['status' => 'suspended']);

        $this->logActivity('tenant_suspended', 'tenants', $tenant->tenant_id);

        return $this->successResponse($tenant, 'Tenant suspended successfully');
    }

    /**
     * Activate tenant
     */
    public function activate(int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        $tenant->update(['status' => 'active']);

        $this->logActivity('tenant_activated', 'tenants', $tenant->tenant_id);

        return $this->successResponse($tenant, 'Tenant activated successfully');
    }

    /**
     * Get tenant statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'trial_tenants' => Tenant::where('is_trial', true)->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'tenants_by_plan' => Tenant::selectRaw('subscription_plan, COUNT(*) as count')
                ->groupBy('subscription_plan')
                ->get(),
            'recent_tenants' => Tenant::recent(30)->count(),
            'total_revenue' => \App\Models\TenantBillingHistory::where('payment_status', 'paid')->sum('total_amount')
        ];

        return $this->successResponse($stats, 'Tenant statistics retrieved successfully');
    }

    /**
     * Get subscription limits for a plan
     */
    private function getSubscriptionLimits(string $plan): array
    {
        return match($plan) {
            'trial' => [
                'max_users' => 5,
                'max_products' => 100,
                'max_warehouses' => 1,
                'max_shops' => 1,
                'storage_limit_gb' => 1,
                'api_requests_limit' => 1000
            ],
            'basic' => [
                'max_users' => 10,
                'max_products' => 500,
                'max_warehouses' => 2,
                'max_shops' => 3,
                'storage_limit_gb' => 5,
                'api_requests_limit' => 5000
            ],
            'premium' => [
                'max_users' => 50,
                'max_products' => 2000,
                'max_warehouses' => 5,
                'max_shops' => 10,
                'storage_limit_gb' => 20,
                'api_requests_limit' => 20000
            ],
            'enterprise' => [
                'max_users' => null, // Unlimited
                'max_products' => null,
                'max_warehouses' => null,
                'max_shops' => null,
                'storage_limit_gb' => 100,
                'api_requests_limit' => 100000
            ],
            default => []
        };
    }

    /**
     * Create default configurations for new tenant
     */
    private function createDefaultConfigurations(Tenant $tenant): void
    {
        $defaultConfigs = [
            ['config_group' => 'system', 'config_key' => 'default_currency', 'config_value' => 'RWF'],
            ['config_group' => 'system', 'config_key' => 'default_timezone', 'config_value' => 'Africa/Kigali'],
            ['config_group' => 'system', 'config_key' => 'default_language', 'config_value' => 'en'],
            ['config_group' => 'inventory', 'config_key' => 'low_stock_alert', 'config_value' => 'true'],
            ['config_group' => 'inventory', 'config_key' => 'auto_reorder', 'config_value' => 'false'],
            ['config_group' => 'email', 'config_key' => 'notifications_enabled', 'config_value' => 'true'],
            ['config_group' => 'security', 'config_key' => 'session_timeout', 'config_value' => '120'], // minutes
            ['config_group' => 'security', 'config_key' => 'password_expiry_days', 'config_value' => '90']
        ];

        foreach ($defaultConfigs as $config) {
            \App\Models\SystemConfiguration::create([
                'tenant_id' => $tenant->tenant_id,
                'config_group' => $config['config_group'],
                'config_key' => $config['config_key'],
                'config_value' => $config['config_value'],
                'data_type' => 'string'
            ]);
        }
    }
}
