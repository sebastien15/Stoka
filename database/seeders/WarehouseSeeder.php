<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\Tenant;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $warehouses = [];

            switch ($tenant->tenant_code) {
                case 'DEMO001': // TechWorld Electronics
                    $warehouses = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Main Electronics Warehouse',
                            'code' => 'WH-MAIN-001',
                            'address' => '1000 Industrial Blvd',
                            'city' => 'San Jose',
                            'state' => 'California',
                            'postal_code' => '95110',
                            'country' => 'United States',
                            'phone_number' => '+1-555-1001',
                            'email' => 'warehouse@techworld.com',
                            'capacity' => 5000.00,
                            'current_utilization' => 65.5,
                            'warehouse_type' => 'distribution',
                            'is_active' => true,
                            'operating_hours' => '6:00 AM - 10:00 PM',
                            'temperature_controlled' => true,
                            'security_level' => 'high',
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Secondary Storage',
                            'code' => 'WH-SEC-002',
                            'address' => '2000 Storage Ave',
                            'city' => 'Fremont',
                            'state' => 'California',
                            'postal_code' => '94538',
                            'country' => 'United States',
                            'phone_number' => '+1-555-1002',
                            'email' => 'storage@techworld.com',
                            'capacity' => 3000.00,
                            'current_utilization' => 45.2,
                            'warehouse_type' => 'storage',
                            'is_active' => true,
                            'operating_hours' => '8:00 AM - 6:00 PM',
                            'temperature_controlled' => false,
                            'security_level' => 'medium',
                        ],
                    ];
                    break;

                case 'DEMO002': // Fashion Forward Boutique
                    $warehouses = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fashion Central Warehouse',
                            'code' => 'WH-FASH-001',
                            'address' => '500 Garment District',
                            'city' => 'New York',
                            'state' => 'New York',
                            'postal_code' => '10018',
                            'country' => 'United States',
                            'phone_number' => '+1-555-2001',
                            'email' => 'warehouse@fashionforward.com',
                            'capacity' => 1500.00,
                            'current_utilization' => 78.3,
                            'warehouse_type' => 'fashion',
                            'is_active' => true,
                            'operating_hours' => '7:00 AM - 7:00 PM',
                            'temperature_controlled' => false,
                            'security_level' => 'medium',
                        ],
                    ];
                    break;

                case 'TRIAL001': // Green Grocers Market
                    $warehouses = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fresh Produce Warehouse',
                            'code' => 'WH-PROD-001',
                            'address' => '100 Fresh Market Lane',
                            'city' => 'San Francisco',
                            'state' => 'California',
                            'postal_code' => '94107',
                            'country' => 'United States',
                            'phone_number' => '+1-555-3001',
                            'email' => 'fresh@greengrocers.com',
                            'capacity' => 800.00,
                            'current_utilization' => 55.0,
                            'warehouse_type' => 'cold_storage',
                            'is_active' => true,
                            'operating_hours' => '5:00 AM - 8:00 PM',
                            'temperature_controlled' => true,
                            'security_level' => 'low',
                        ],
                    ];
                    break;
            }

            foreach ($warehouses as $warehouseData) {
                Warehouse::create($warehouseData);
            }
        }

        $this->command->info('Created warehouses for all tenants successfully.');
    }
}