<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $warehouses = $tenant->warehouses;
            $shops = $tenant->shops;

            $users = [];

            switch ($tenant->tenant_code) {
                case 'DEMO001': // TechWorld Electronics demo data
                    $users = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'full_name' => 'John Smith',
                            'email' => 'admin@techworld.com',
                            'password' => Hash::make('password123'),
                            'phone_number' => '+1-555-0101',
                            'address' => '123 Tech Street, Palo Alto, CA',
                            'role' => 'tenant_admin',
                            'warehouse_id' => null,
                            'shop_id' => null,
                            'is_active' => true,
                            'last_login' => now()->subDays(1),
                            'date_of_birth' => '1985-03-15',
                            'gender' => 'male',
                            'emergency_contact' => '+1-555-0199',
                            'salary' => 85000.00,
                            'hire_date' => now()->subYears(3),
                            'permissions' => [
                                'users.view', 'users.create', 'users.edit', 'users.delete',
                                'products.view', 'products.create', 'products.edit', 'products.delete',
                                'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
                                'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                                'orders.view', 'orders.create', 'orders.edit', 'orders.delete',
                                'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
                                'reports.view', 'settings.manage'
                            ],
                            'access_level' => 'full',
                            'mfa_enabled' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'full_name' => 'Alice Johnson',
                            'email' => 'alice.johnson@techworld.com',
                            'password' => Hash::make('password123'),
                            'phone_number' => '+1-555-1010',
                            'address' => '456 Innovation Drive, San Jose, CA',
                            'role' => 'warehouse_manager',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_id' => null,
                            'is_active' => true,
                            'last_login' => now()->subHours(8),
                            'date_of_birth' => '1988-07-22',
                            'gender' => 'female',
                            'emergency_contact' => '+1-555-1099',
                            'salary' => 65000.00,
                            'hire_date' => now()->subYears(2),
                            'permissions' => [
                                'products.view', 'products.edit',
                                'inventory.view', 'inventory.manage',
                                'purchases.view', 'purchases.create',
                                'warehouse.manage'
                            ],
                            'access_level' => 'warehouse',
                            'mfa_enabled' => false,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'full_name' => 'Robert Chen',
                            'email' => 'robert.chen@techworld.com',
                            'password' => Hash::make('password123'),
                            'phone_number' => '+1-555-2010',
                            'address' => '789 Retail Plaza, Palo Alto, CA',
                            'role' => 'shop_manager',
                            'warehouse_id' => null,
                            'shop_id' => $shops->first()->shop_id ?? null,
                            'is_active' => true,
                            'last_login' => now()->subHours(4),
                            'date_of_birth' => '1990-11-08',
                            'gender' => 'male',
                            'emergency_contact' => '+1-555-2099',
                            'salary' => 55000.00,
                            'hire_date' => now()->subYears(1),
                            'permissions' => [
                                'products.view',
                                'orders.view', 'orders.create', 'orders.edit',
                                'customers.view', 'customers.create',
                                'shop.manage'
                            ],
                            'access_level' => 'shop',
                            'mfa_enabled' => false,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'full_name' => 'David Wilson',
                            'email' => 'david.wilson@example.com',
                            'password' => Hash::make('password123'),
                            'phone_number' => '+1-555-4010',
                            'address' => '654 Customer Street, San Francisco, CA',
                            'role' => 'customer',
                            'warehouse_id' => null,
                            'shop_id' => null,
                            'is_active' => true,
                            'last_login' => now()->subDays(3),
                            'date_of_birth' => '1987-04-30',
                            'gender' => 'male',
                            'emergency_contact' => '+1-555-4099',
                            'salary' => null,
                            'hire_date' => null,
                            'permissions' => [],
                            'access_level' => 'customer',
                            'mfa_enabled' => false,
                        ],
                    ];
                    break;

                default:
                    $users = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'full_name' => $tenant->contact_person,
                            'email' => $tenant->email,
                            'password' => Hash::make('password123'),
                            'phone_number' => $tenant->phone_number,
                            'address' => $tenant->address,
                            'role' => 'tenant_admin',
                            'warehouse_id' => null,
                            'shop_id' => null,
                            'is_active' => true,
                            'last_login' => now()->subHours(2),
                            'permissions' => [
                                'users.view', 'users.create', 'users.edit',
                                'products.view', 'products.create', 'products.edit',
                                'categories.view', 'categories.create',
                                'suppliers.view', 'suppliers.create',
                                'orders.view', 'orders.create', 'orders.edit',
                                'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
                                'reports.view'
                            ],
                            'access_level' => 'full',
                            'mfa_enabled' => false,
                        ],
                    ];
                    break;
            }

            // ✅ Add warehouse manager if tenant has warehouses
            if ($warehouses->isNotEmpty()) {
                $users[] = [
                    'tenant_id' => $tenant->tenant_id,
                    'full_name' => 'Warehouse Manager - ' . $tenant->company_name,
                    'email' => strtolower($tenant->tenant_code) . '.warehouse@system.com',
                    'password' => Hash::make('password123'),
                    'phone_number' => '+1-555-' . rand(1000, 9999),
                    'address' => $tenant->address,
                    'role' => 'warehouse_manager',
                    'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                    'shop_id' => null,
                    'is_active' => true,
                    'last_login' => now()->subHours(rand(1, 12)),
                    'permissions' => [
                        'products.view', 'products.edit',
                        'inventory.view', 'inventory.manage',
                        'purchases.view', 'purchases.create',
                        'warehouse.manage'
                    ],
                    'access_level' => 'warehouse',
                    'mfa_enabled' => false,
                ];
            }

            // ✅ Add shop manager if tenant has shops
            if ($shops->isNotEmpty()) {
                $users[] = [
                    'tenant_id' => $tenant->tenant_id,
                    'full_name' => 'Shop Manager - ' . $tenant->company_name,
                    'email' => strtolower($tenant->tenant_code) . '.shop@system.com',
                    'password' => Hash::make('password123'),
                    'phone_number' => '+1-555-' . rand(1000, 9999),
                    'address' => $tenant->address,
                    'role' => 'shop_manager',
                    'warehouse_id' => null,
                    'shop_id' => $shops->first()->shop_id ?? null,
                    'is_active' => true,
                    'last_login' => now()->subHours(rand(1, 12)),
                    'permissions' => [
                        'products.view',
                        'orders.view', 'orders.create', 'orders.edit',
                        'customers.view', 'customers.create',
                        'shop.manage'
                    ],
                    'access_level' => 'shop',
                    'mfa_enabled' => false,
                ];
            }

            // ✅ Create all users
            foreach ($users as $userData) {
                User::create($userData);
            }

            // ✅ Update warehouse and shop managers
            if ($warehouses->isNotEmpty()) {
                $warehouseManager = $tenant->users()->where('role', 'warehouse_manager')->first();
                if ($warehouseManager) {
                    $warehouses->first()->update(['manager_id' => $warehouseManager->user_id]);
                }
            }

            if ($shops->isNotEmpty()) {
                $shopManager = $tenant->users()->where('role', 'shop_manager')->first();
                if ($shopManager) {
                    $shops->first()->update(['manager_id' => $shopManager->user_id]);
                }
            }
        }

        $this->command->info('✅ Created users, warehouse managers, and shop managers for all tenants.');

        // ✅ Create Super Admin (global, not tied to any tenant)
        $superAdminEmail = 'superadmin@example.com';
        if (!User::where('email', $superAdminEmail)->exists()) {
            User::create([
                'tenant_id' => null,
                'full_name' => 'Super Admin',
                'email' => $superAdminEmail,
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'is_active' => true,
                'last_login' => now()->subDay(),
                'access_level' => 'full',
                'mfa_enabled' => false,
                'permissions' => [] // super_admin bypasses checks
            ]);

            $this->command->info('✅ Created Super Admin: superadmin@example.com / password123');
        }
    }
}
