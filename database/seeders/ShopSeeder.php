<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shop;
use App\Models\Tenant;
use App\Models\Warehouse;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $warehouses = $tenant->warehouses;
            $shops = [];

            switch ($tenant->tenant_code) {
                case 'DEMO001': // TechWorld Electronics
                    $shops = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'TechWorld Downtown Store',
                            'code' => 'SH-TECH-001',
                            'address' => '500 Main Street',
                            'city' => 'Palo Alto',
                            'state' => 'California',
                            'postal_code' => '94301',
                            'country' => 'United States',
                            'phone_number' => '+1-555-5001',
                            'email' => 'downtown@techworld.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'retail',
                            'floor_area' => 2500.00,
                            'rent_amount' => 8500.00,
                            'is_active' => true,
                            'opening_hours' => '9:00 AM - 9:00 PM',
                            'website_url' => 'https://techworld.com/downtown',
                            'social_media_handles' => [
                                'instagram' => '@techworld_downtown',
                                'facebook' => 'TechWorldDowntown',
                                'twitter' => '@TechWorldDT'
                            ],
                            'pos_system' => 'Square',
                            'online_shop_enabled' => true,
                            'delivery_enabled' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'TechWorld Mall Outlet',
                            'code' => 'SH-TECH-002',
                            'address' => '1200 Shopping Mall Way, Unit 145',
                            'city' => 'San Jose',
                            'state' => 'California',
                            'postal_code' => '95128',
                            'country' => 'United States',
                            'phone_number' => '+1-555-5002',
                            'email' => 'mall@techworld.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'outlet',
                            'floor_area' => 1800.00,
                            'rent_amount' => 6200.00,
                            'is_active' => true,
                            'opening_hours' => '10:00 AM - 10:00 PM',
                            'website_url' => 'https://techworld.com/mall',
                            'social_media_handles' => [
                                'instagram' => '@techworld_mall',
                                'facebook' => 'TechWorldMall'
                            ],
                            'pos_system' => 'Shopify POS',
                            'online_shop_enabled' => false,
                            'delivery_enabled' => false,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'TechWorld Online Store',
                            'code' => 'SH-TECH-003',
                            'address' => '123 Tech Street (Virtual)',
                            'city' => 'Palo Alto',
                            'state' => 'California',
                            'postal_code' => '94301',
                            'country' => 'United States',
                            'phone_number' => '+1-555-5003',
                            'email' => 'online@techworld.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'online',
                            'floor_area' => 0.00,
                            'rent_amount' => 0.00,
                            'is_active' => true,
                            'opening_hours' => '24/7',
                            'website_url' => 'https://shop.techworld.com',
                            'social_media_handles' => [
                                'instagram' => '@techworld_official',
                                'facebook' => 'TechWorldOfficial',
                                'twitter' => '@TechWorld',
                                'youtube' => 'TechWorldTV'
                            ],
                            'pos_system' => 'WooCommerce',
                            'online_shop_enabled' => true,
                            'delivery_enabled' => true,
                        ],
                    ];
                    break;

                case 'DEMO002': // Fashion Forward Boutique
                    $shops = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fashion Forward Flagship',
                            'code' => 'SH-FASH-001',
                            'address' => '456 Fashion Avenue',
                            'city' => 'New York',
                            'state' => 'New York',
                            'postal_code' => '10001',
                            'country' => 'United States',
                            'phone_number' => '+1-555-6001',
                            'email' => 'flagship@fashionforward.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'boutique',
                            'floor_area' => 1200.00,
                            'rent_amount' => 12000.00,
                            'is_active' => true,
                            'opening_hours' => '10:00 AM - 8:00 PM',
                            'website_url' => 'https://fashionforward.com/flagship',
                            'social_media_handles' => [
                                'instagram' => '@fashionforward_nyc',
                                'facebook' => 'FashionForwardNYC',
                                'pinterest' => 'FashionForwardStyle'
                            ],
                            'pos_system' => 'Clover',
                            'online_shop_enabled' => true,
                            'delivery_enabled' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fashion Forward Online',
                            'code' => 'SH-FASH-002',
                            'address' => '456 Fashion Avenue (Virtual)',
                            'city' => 'New York',
                            'state' => 'New York',
                            'postal_code' => '10001',
                            'country' => 'United States',
                            'phone_number' => '+1-555-6002',
                            'email' => 'online@fashionforward.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'online',
                            'floor_area' => 0.00,
                            'rent_amount' => 0.00,
                            'is_active' => true,
                            'opening_hours' => '24/7',
                            'website_url' => 'https://shop.fashionforward.com',
                            'social_media_handles' => [
                                'instagram' => '@fashionforward_shop',
                                'facebook' => 'FashionForwardOnline',
                                'tiktok' => '@fashionforward_ff'
                            ],
                            'pos_system' => 'Shopify',
                            'online_shop_enabled' => true,
                            'delivery_enabled' => true,
                        ],
                    ];
                    break;

                case 'TRIAL001': // Green Grocers Market
                    $shops = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Green Grocers Main Store',
                            'code' => 'SH-GREEN-001',
                            'address' => '789 Market Street',
                            'city' => 'San Francisco',
                            'state' => 'California',
                            'postal_code' => '94102',
                            'country' => 'United States',
                            'phone_number' => '+1-555-7001',
                            'email' => 'store@greengrocers.com',
                            'warehouse_id' => $warehouses->first()->warehouse_id ?? null,
                            'shop_type' => 'grocery',
                            'floor_area' => 3500.00,
                            'rent_amount' => 7500.00,
                            'is_active' => true,
                            'opening_hours' => '6:00 AM - 11:00 PM',
                            'website_url' => 'https://greengrocers.com',
                            'social_media_handles' => [
                                'instagram' => '@green_grocers_sf',
                                'facebook' => 'GreenGrocersSF'
                            ],
                            'pos_system' => 'Toast POS',
                            'online_shop_enabled' => false,
                            'delivery_enabled' => true,
                        ],
                    ];
                    break;
            }

            foreach ($shops as $shopData) {
                Shop::create($shopData);
            }
        }

        $this->command->info('Created shops for all tenants successfully.');
    }
}