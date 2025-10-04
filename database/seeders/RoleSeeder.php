<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access across all tenants',
                'is_system_role' => true,
                'is_active' => true,
                'default_permissions' => []
            ],
            [
                'name' => 'tenant_admin',
                'display_name' => 'Tenant Administrator',
                'description' => 'Full access within tenant scope',
                'is_system_role' => false,
                'is_active' => true,
                'default_permissions' => [
                    'users.view','users.create','users.edit','users.delete','users.manage_permissions',
                    'customers.view','customers.create','customers.edit','customers.delete',
                    'products.view','products.create','products.edit','products.delete','products.manage_stock',
                    'orders.view','orders.create','orders.edit','orders.delete','orders.manage',
                    'purchases.view','purchases.create','purchases.edit','purchases.delete','purchases.manage',
                    'warehouses.view','warehouses.create','warehouses.edit','warehouses.delete',
                    'shops.view','shops.create','shops.edit','shops.delete',
                    'categories.view','categories.create','categories.edit','categories.delete',
                    'brands.view','brands.create','brands.edit','brands.delete',
                    'suppliers.view','suppliers.create','suppliers.edit','suppliers.delete',
                    'expenses.view','expenses.create','expenses.edit','expenses.delete',
                    'inventory.view','inventory.adjust',
                    'notices.view','notices.create','notices.edit','notices.delete',
                    'audit.view','audit.export',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'warehouse_manager',
                'display_name' => 'Warehouse Manager',
                'description' => 'Manage warehouse operations and inventory',
                'is_system_role' => false,
                'is_active' => true,
                'default_permissions' => [
                    'products.view','products.create','products.edit','products.manage_stock',
                    'inventory.view','inventory.adjust',
                    'warehouses.view','warehouses.transfer',
                    'purchases.view','purchases.create','purchases.edit','purchases.receive',
                    'orders.view','orders.create','orders.edit',
                    'suppliers.view','suppliers.create','suppliers.edit',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'shop_manager',
                'display_name' => 'Shop Manager',
                'description' => 'Manage shop operations and sales',
                'is_system_role' => false,
                'is_active' => true,
                'default_permissions' => [
                    'products.view','products.create','products.edit',
                    'customers.view','customers.create','customers.edit',
                    'orders.view','orders.create','orders.edit','orders.manage',
                    'shops.view','shops.edit',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'employee',
                'display_name' => 'Employee',
                'description' => 'Basic operational access',
                'is_system_role' => false,
                'is_active' => true,
                'default_permissions' => [
                    'products.view',
                    'orders.view','orders.create',
                    'inventory.view',
                    'dashboard.view'
                ]
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Customer access only',
                'is_system_role' => false,
                'is_active' => true,
                'default_permissions' => [
                    'products.view',
                    'orders.view','orders.create'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'] ?? null,
                    'is_system_role' => $roleData['is_system_role'] ?? false,
                    'is_active' => $roleData['is_active'] ?? true,
                    'default_permissions' => $roleData['default_permissions'] ?? []
                ]
            );

            if (!empty($roleData['default_permissions'])) {
                $role->syncPermissions($roleData['default_permissions']);
            }
        }
    }
}


