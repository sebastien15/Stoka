<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('roles.view');
        $this->requireTenant();

        $query = Role::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_system_role')) {
            $query->where('is_system_role', $request->boolean('is_system_role'));
        }

        $query->with('permissions');
        return $this->paginatedResponse($query, $request, 'Roles retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('roles.create');
        $this->requireTenant();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles,name',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $roleData = $validator->validated();
            $roleData['is_system_role'] = false;
            $role = Role::create($roleData);

            if (!empty($roleData['permissions'])) {
                $role->syncPermissions($roleData['permissions']);
            }

            $this->logActivity('role_created', 'roles', $role->role_id, ['role' => $role->toArray()]);
            DB::commit();

            $role->load('permissions');
            return $this->successResponse($role, 'Role created successfully', 201);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to create role: ' . $e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $this->requirePermission('roles.view');
        $this->requireTenant();

        $role = Role::with('permissions')->find($id);
        if (!$role) {
            return $this->errorResponse('Role not found', 404);
        }
        return $this->successResponse($role, 'Role retrieved successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->requirePermission('roles.edit');
        $this->requireTenant();

        $role = Role::find($id);
        if (!$role) {
            return $this->errorResponse('Role not found', 404);
        }
        if ($role->name === 'super_admin') {
            return $this->errorResponse('Cannot modify super admin role', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50|unique:roles,name,' . $id . ',role_id',
            'display_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();

            $oldData = $role->toArray();
            $role->update($validator->validated());

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            $this->logActivity('role_updated', 'roles', $role->role_id, [
                'old_data' => $oldData,
                'new_data' => $role->toArray()
            ]);

            DB::commit();

            $role->load('permissions');
            return $this->successResponse($role, 'Role updated successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->requirePermission('roles.delete');
        $this->requireTenant();

        $role = Role::find($id);
        if (!$role) {
            return $this->errorResponse('Role not found', 404);
        }
        if ($role->name === 'super_admin') {
            return $this->errorResponse('Cannot delete super admin role', 403);
        }
        if (!$role->canBeDeleted()) {
            return $this->errorResponse('Cannot delete role that is assigned to users', 400);
        }

        try {
            DB::beginTransaction();
            $roleData = $role->toArray();
            $role->delete();

            $this->logActivity('role_deleted', 'roles', $id, ['deleted_role' => $roleData]);
            DB::commit();
            return $this->successResponse(null, 'Role deleted successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to delete role: ' . $e->getMessage(), 500);
        }
    }

    public function permissions(int $id): JsonResponse
    {
        $this->requirePermission('roles.view');
        $this->requireTenant();

        $role = Role::with('permissions')->find($id);
        if (!$role) {
            return $this->errorResponse('Role not found', 404);
        }

        $permissions = [
            'role' => $role->toArray(),
            'permissions' => $role->getPermissions(),
            'permission_count' => count($role->getPermissions())
        ];

        return $this->successResponse($permissions, 'Role permissions retrieved successfully');
    }

    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $this->requirePermission('roles.manage_permissions');
        $this->requireTenant();

        $role = Role::find($id);
        if (!$role) {
            return $this->errorResponse('Role not found', 404);
        }
        if ($role->name === 'super_admin') {
            return $this->errorResponse('Cannot modify super admin role permissions', 403);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'string|max:100'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();
            $oldPermissions = $role->getPermissions();
            $role->syncPermissions($request->permissions);
            $this->logActivity('role_permissions_updated', 'roles', $role->role_id, [
                'old_permissions' => $oldPermissions,
                'new_permissions' => $role->getPermissions()
            ]);
            DB::commit();
            $role->load('permissions');
            return $this->successResponse($role, 'Role permissions updated successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to update role permissions: ' . $e->getMessage(), 500);
        }
    }

    public function permissionsCatalog(): JsonResponse
    {
        $this->requirePermission('roles.view');
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


