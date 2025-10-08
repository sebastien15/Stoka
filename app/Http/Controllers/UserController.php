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



class UserController extends BaseController

{

    /**

     * Display a listing of users

     */

    public function index(Request $request): JsonResponse

    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.view');



        $superAdminAll = auth()->check() && auth()->user()->isSuperAdmin() && $request->boolean('all');

        if (!$superAdminAll) {
            $this->requireTenant();
        }

        $query = $superAdminAll ? User::query() : $this->applyTenantScope(User::query());


        // Apply filters

        $query = $this->applyFilters($query, $request, [

            'full_name',

            'email',

            'phone_number'

        ]);



        // Role filter

        if ($request->has('role')) {

            $query->where('role', $request->get('role'));

        }



        // Warehouse filter

        if ($request->has('warehouse_id')) {

            $query->where('warehouse_id', $request->get('warehouse_id'));

        }



        // Shop filter

        if ($request->has('shop_id')) {

            $query->where('shop_id', $request->get('shop_id'));

        }



        // Optimize with eager loading and specific columns
        $query->with([
            'warehouse:id,warehouse_id,name,status,address',
            'shop:id,shop_id,name,status,address',
            'customerProfile:id,customer_id,phone_number,address,date_of_birth,gender,customer_tier,loyalty_points'
        ]);



        return $this->paginatedResponse($query, $request, 'Users retrieved successfully');

    }



    /**

     * Store a newly created user

     */

    public function store(Request $request): JsonResponse

    {

        // Check if user is authenticated for tenant resolution
        $isSuperAdmin = auth()->check() && auth()->user()->isSuperAdmin();
        
        // Only require tenant context for authenticated users (not super admins with tenant_id)
        if (auth()->check() && (!$isSuperAdmin || !$request->has('tenant_id'))) {
            $this->requireTenant();
        }

        // Removed permission check - only requires token authentication
        // if (auth()->check()) {
        //     $this->requirePermission('users.create');
            
        // Check tenant limits only for authenticated users
        if (auth()->check() && !$this->checkTenantLimits('users')) {
            return $this->errorResponse('User limit reached for your subscription plan', 403);
        }



        // Build validation rules based on authentication status
        $validationRules = [
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'role' => 'required|in:tenant_admin,admin,warehouse_manager,shop_manager,employee,customer',
            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',
            'shop_id' => 'nullable|exists:shops,shop_id',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'emergency_contact' => 'nullable|string|max:20',
            'salary' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
            'permissions' => 'nullable|array',
            'access_level' => 'nullable|in:full,limited,read_only',
            'profile_image_url' => 'nullable|url|max:500'
        ];

        // If not authenticated, require tenant_id
        if (!auth()->check()) {
            $validationRules['tenant_id'] = 'required|exists:tenants,tenant_id';
        } else {
            $validationRules['tenant_id'] = 'nullable|exists:tenants,tenant_id';
        }

        $validator = Validator::make($request->all(), $validationRules);



        if ($validator->fails()) {

            return $this->validationErrorResponse($validator);

        }



        try {

            DB::beginTransaction();



            $userData = $validator->validated();

            // Determine target tenant ID
            if (auth()->check() && $isSuperAdmin && $request->has('tenant_id')) {
                // Super admin creating user for specific tenant
                $userData['tenant_id'] = $request->get('tenant_id');
            } elseif (auth()->check()) {
                // Authenticated user creating for their own tenant
                $userData['tenant_id'] = $this->tenant->tenant_id;
            } else {
                // Non-authenticated user must provide tenant_id
                $userData['tenant_id'] = $request->get('tenant_id');
            }

            $userData['password'] = Hash::make($userData['password']);

            $userData['is_active'] = true;



            // Get target tenant for validation
            $targetTenant = Tenant::find($userData['tenant_id']);
            if (!$targetTenant) {
                return $this->errorResponse('Target tenant not found', 400);
            }

            // Validate warehouse and shop belong to target tenant
            if (!empty($userData['warehouse_id'])) {
                $warehouse = $targetTenant->warehouses()->find($userData['warehouse_id']);
                if (!$warehouse) {
                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);
                }
            }

            if (!empty($userData['shop_id'])) {
                $shop = $targetTenant->shops()->find($userData['shop_id']);
                if (!$shop) {
                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);
                }
            }



            $user = User::create($userData);



            // Create customer profile if role is customer

            if ($userData['role'] === 'customer') {

                CustomerProfile::create([

                    'customer_id' => $user->user_id,

                    'tenant_id' => $targetTenant->tenant_id,

                    'phone_number' => $userData['phone_number'] ?? null,

                    'address' => $userData['address'] ?? null,

                    'date_of_birth' => $userData['date_of_birth'] ?? null,

                    'gender' => $userData['gender'] ?? null,

                    'preferred_language' => 'English',

                    'customer_tier' => 'bronze'

                ]);

            }



            $this->logActivity('user_created', 'users', $user->user_id);



            DB::commit();



            // Load relationships for response
            $user->load([
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'customerProfile:id,customer_id,phone_number,address,date_of_birth,gender,customer_tier,loyalty_points'
            ]);

            // Create custom response with target tenant info
            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user,
                'tenant' => [
                    'id' => $targetTenant->tenant_id,
                    'name' => $targetTenant->company_name,
                    'code' => $targetTenant->tenant_code
                ]
            ], 201);



        } catch (\Exception $e) {

            DB::rollback();

            return $this->errorResponse('Failed to create user: ' . $e->getMessage(), 500);

        }

    }



    /**

     * Display the specified user

     */

    public function show(int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.view');



        $user = $this->applyTenantScope(User::query())
            ->with([
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'customerProfile:id,customer_id,phone_number,address,date_of_birth,gender,customer_tier,loyalty_points'
            ])
            ->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        return $this->successResponse($user, 'User retrieved successfully');

    }



    /**

     * Update the specified user

     */

    public function update(Request $request, int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.edit');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $validator = Validator::make($request->all(), [

            'full_name' => 'sometimes|string|max:150',

            'email' => 'sometimes|email|max:150|unique:users,email,' . $id . ',user_id',

            'password' => 'sometimes|string|min:8|confirmed',

            'phone_number' => 'nullable|string|max:20',

            'address' => 'nullable|string',

            'role' => 'sometimes|in:tenant_admin,admin,warehouse_manager,shop_manager,employee,customer',

            'warehouse_id' => 'nullable|exists:warehouses,warehouse_id',

            'shop_id' => 'nullable|exists:shops,shop_id',

            'is_active' => 'sometimes|boolean',

            'date_of_birth' => 'nullable|date',

            'gender' => 'nullable|in:male,female,other',

            'emergency_contact' => 'nullable|string|max:20',

            'salary' => 'nullable|numeric|min:0',

            'hire_date' => 'nullable|date',

            'permissions' => 'nullable|array',

            'access_level' => 'nullable|in:full,limited,read_only',

            'profile_image_url' => 'nullable|url|max:500'

        ]);



        if ($validator->fails()) {

            return $this->validationErrorResponse($validator);

        }



        try {

            DB::beginTransaction();



            $oldValues = $user->toArray();

            $userData = $validator->validated();



            // Hash password if provided

            if (isset($userData['password'])) {

                $userData['password'] = Hash::make($userData['password']);

            }



            // Validate warehouse and shop belong to tenant

            if (isset($userData['warehouse_id']) && $userData['warehouse_id']) {

                $warehouse = $this->tenant->warehouses()->find($userData['warehouse_id']);

                if (!$warehouse) {

                    return $this->errorResponse('Warehouse not found or does not belong to tenant', 400);

                }

            }



            if (isset($userData['shop_id']) && $userData['shop_id']) {

                $shop = $this->tenant->shops()->find($userData['shop_id']);

                if (!$shop) {

                    return $this->errorResponse('Shop not found or does not belong to tenant', 400);

                }

            }



            $user->update($userData);



            // Update customer profile if role changed to/from customer

            if (isset($userData['role'])) {

                if ($userData['role'] === 'customer' && !$user->customerProfile) {

                    CustomerProfile::create([

                        'customer_id' => $user->user_id,

                        'tenant_id' => $this->tenant->tenant_id,

                        'phone_number' => $user->phone_number,

                        'address' => $user->address,

                        'date_of_birth' => $user->date_of_birth,

                        'gender' => $user->gender,

                        'preferred_language' => 'English',

                        'customer_tier' => 'bronze'

                    ]);

                } elseif ($userData['role'] !== 'customer' && $user->customerProfile) {

                    $user->customerProfile->delete();

                }

            }



            $this->logActivity('user_updated', 'users', $user->user_id, [

                'old_values' => $oldValues,

                'new_values' => $user->toArray()

            ]);



            DB::commit();



            $user->load([
                'warehouse:id,warehouse_id,name,status,address',
                'shop:id,shop_id,name,status,address',
                'customerProfile:id,customer_id,phone_number,address,date_of_birth,gender,customer_tier,loyalty_points'
            ]);



            return $this->successResponse($user, 'User updated successfully');



        } catch (\Exception $e) {

            DB::rollback();

            return $this->errorResponse('Failed to update user: ' . $e->getMessage(), 500);

        }

    }



    /**

     * Remove the specified user

     */

    public function destroy(Request $request, int $id): JsonResponse

    {

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.delete');



        // Super admin bypass for cross-tenant deletion

        $superAdminAll = auth()->check() && auth()->user()->isSuperAdmin() && $request->boolean('all');

        if (!$superAdminAll) {

            $this->requireTenant();

        }



        $user = $superAdminAll ? User::query()->find($id) : $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        // Prevent deletion of the last admin in the tenant

        if ($user->isAdmin()) {

            $query = $superAdminAll ? 

                User::where('tenant_id', $user->tenant_id) :

                $this->applyTenantScope(User::query());

                

            $adminCount = $query

                ->whereIn('role', ['tenant_admin', 'admin'])

                ->count();

            

            if ($adminCount <= 1) {

                return $this->errorResponse('Cannot delete the last administrator', 400);

            }

        }



        // Prevent deletion of super admins

        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {

            return $this->errorResponse('Cannot delete super admin user', 403);

        }



        try {

            DB::beginTransaction();



            $userData = $user->toArray();

            

            // Delete customer profile if exists

            if ($user->customerProfile) {

                $user->customerProfile->delete();

            }



            $user->delete();



            $this->logActivity('user_deleted', 'users', $id, ['deleted_user' => $userData]);



            DB::commit();



            return $this->successResponse(null, 'User deleted successfully');



        } catch (\Exception $e) {

            DB::rollback();

            return $this->errorResponse('Failed to delete user: ' . $e->getMessage(), 500);

        }

    }



    /**

     * Activate user

     */

    public function activate(int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.edit');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $user->activate();

        $this->logActivity('user_activated', 'users', $user->user_id);



        return $this->successResponse($user, 'User activated successfully');

    }



    /**

     * Deactivate user

     */

    public function deactivate(int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.edit');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $user->deactivate();

        $this->logActivity('user_deactivated', 'users', $user->user_id);



        return $this->successResponse($user, 'User deactivated successfully');

    }



    /**

     * Reset user password

     */

    public function resetPassword(Request $request, int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.edit');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $validator = Validator::make($request->all(), [

            'password' => 'required|string|min:8|confirmed'

        ]);



        if ($validator->fails()) {

            return $this->validationErrorResponse($validator);

        }



        $user->update([

            'password' => Hash::make($request->password)

        ]);



        $this->logActivity('password_reset', 'users', $user->user_id);



        return $this->successResponse(null, 'Password reset successfully');

    }



    /**

     * Get user permissions

     */

    public function permissions(int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.view');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $permissions = [

            'role' => $user->role,

            'access_level' => $user->access_level,

            'permissions' => $user->getPermissions(),

            'is_admin' => $user->isAdmin(),

            'is_manager' => $user->isManager(),

            'can_manage_users' => $user->hasPermission('users.manage'),

            'can_manage_products' => $user->hasPermission('products.manage'),

            'can_manage_orders' => $user->hasPermission('orders.manage')

        ];



        return $this->successResponse($permissions, 'User permissions retrieved successfully');

    }



    /**

     * Update user permissions

     */

    public function updatePermissions(Request $request, int $id): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.manage_permissions');



        $user = $this->applyTenantScope(User::query())->find($id);



        if (!$user) {

            return $this->errorResponse('User not found', 404);

        }



        $validator = Validator::make($request->all(), [

            'permissions' => 'required|array',

            'access_level' => 'sometimes|in:full,limited,read_only'

        ]);



        if ($validator->fails()) {

            return $this->validationErrorResponse($validator);

        }



        $oldPermissions = $user->getPermissions();

        $user->permissions = $request->permissions;



        if ($request->has('access_level')) {

            $user->access_level = $request->access_level;

        }



        $user->save();



        $this->logActivity('permissions_updated', 'users', $user->user_id, [

            'old_permissions' => $oldPermissions,

            'new_permissions' => $user->getPermissions()

        ]);



        return $this->successResponse($user, 'User permissions updated successfully');

    }



    /**

     * Get user statistics

     */

    public function stats(): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.view');



        $stats = [

            'total_users' => $this->applyTenantScope(User::query())->count(),

            'active_users' => $this->applyTenantScope(User::query())->active()->count(),

            'inactive_users' => $this->applyTenantScope(User::query())->inactive()->count(),

            'admins' => $this->applyTenantScope(User::query())->admins()->count(),

            'managers' => $this->applyTenantScope(User::query())->managers()->count(),

            'employees' => $this->applyTenantScope(User::query())->employees()->count(),

            'customers' => $this->applyTenantScope(User::query())->customers()->count(),

            'recent_logins' => $this->applyTenantScope(User::query())

                ->where('last_login', '>=', now()->subDays(7))

                ->count(),

            'users_with_mfa' => $this->applyTenantScope(User::query())->withMFA()->count()

        ];



        return $this->successResponse($stats, 'User statistics retrieved successfully');

    }



    /**

     * Get available roles

     */

    public function roles(): JsonResponse

    {

        $roles = [

            'tenant_admin' => 'Tenant Administrator',

            'admin' => 'Administrator',

            'warehouse_manager' => 'Warehouse Manager',

            'shop_manager' => 'Shop Manager',

            'employee' => 'Employee',

            'customer' => 'Customer'

        ];



        return $this->successResponse($roles, 'Available roles retrieved successfully');

    }



    /**

     * Bulk operations

     */

    public function bulkAction(Request $request): JsonResponse

    {

        $this->requireTenant();

        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.bulk_actions');



        $validator = Validator::make($request->all(), [

            'action' => 'required|in:activate,deactivate,delete',

            'user_ids' => 'required|array|min:1',

            'user_ids.*' => 'integer|exists:users,user_id'

        ]);



        if ($validator->fails()) {

            return $this->validationErrorResponse($validator);

        }



        $users = $this->applyTenantScope(User::query())

            ->whereIn('user_id', $request->user_ids)

            ->get();



        if ($users->count() !== count($request->user_ids)) {

            return $this->errorResponse('Some users not found or do not belong to tenant', 400);

        }



        try {

            DB::beginTransaction();



            $results = [];

            foreach ($users as $user) {

                switch ($request->action) {

                    case 'activate':

                        $user->activate();

                        $results[] = "User {$user->full_name} activated";

                        break;

                    case 'deactivate':

                        $user->deactivate();

                        $results[] = "User {$user->full_name} deactivated";

                        break;

                    case 'delete':

                        // Prevent deletion of admins in bulk

                        if (!$user->isAdmin()) {

                            $user->delete();

                            $results[] = "User {$user->full_name} deleted";

                        } else {

                            $results[] = "Admin {$user->full_name} skipped (cannot delete)";

                        }

                        break;

                }

            }



            $this->logActivity('bulk_user_action', 'users', null, [

                'action' => $request->action,

                'user_ids' => $request->user_ids,

                'results' => $results

            ]);



            DB::commit();



            return $this->successResponse($results, 'Bulk action completed successfully');



        } catch (\Exception $e) {

            DB::rollback();

            return $this->errorResponse('Bulk action failed: ' . $e->getMessage(), 500);

        }

    }

    /**
     * Get permissions catalog
     */
    public function permissionsCatalog(): JsonResponse
    {
        // Removed permission check - only requires token authentication
        // $this->requirePermission('users.view');
        $this->requireTenant();

        $catalog = [
            'users' => ['users.view','users.create','users.edit','users.delete','users.manage_permissions','users.bulk_actions'],
            'customers' => ['customers.view','customers.create','customers.edit','customers.delete'],
            'products' => ['products.view','products.create','products.edit','products.delete','products.manage_stock','products.bulk_actions'],
            'orders' => ['orders.view','orders.create','orders.edit','orders.delete','orders.manage','orders.manage_payment'],
            'purchases' => ['purchases.view','purchases.create','purchases.edit','purchases.delete','purchases.manage','purchases.receive','purchases.manage_payment'],
            'warehouses' => ['warehouses.view','warehouses.create','warehouses.edit','warehouses.delete','warehouses.transfer'],
            'shops' => ['shops.view','shops.create','shops.edit','shops.delete'],
            'categories' => ['categories.view','categories.create','categories.edit','categories.delete','categories.bulk_actions'],
            'brands' => ['brands.view','brands.create','brands.edit','brands.delete'],
            'suppliers' => ['suppliers.view','suppliers.create','suppliers.edit','suppliers.delete'],
            'expenses' => ['expenses.view','expenses.create','expenses.edit','expenses.delete','expenses.approve','expenses.manage_payment'],
            'inventory' => ['inventory.view','inventory.adjust'],
            'notices' => ['notices.view','notices.create','notices.edit','notices.delete','notices.publish'],
            'audit' => ['audit.view','audit.export','audit.cleanup'],
            'dashboard' => ['dashboard.view'],
            'roles' => ['roles.view','roles.create','roles.edit','roles.delete','roles.manage_permissions']
        ];

        return $this->successResponse($catalog, 'Permissions catalog retrieved successfully');
    }

}

