<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\Shop;
use App\Models\Warehouse;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $categories = $tenant->categories()->get();
            $suppliers = $tenant->suppliers;
            $shops = $tenant->shops;
            $warehouses = $tenant->warehouses;

            if ($categories->isEmpty() || $suppliers->isEmpty() || $shops->isEmpty() || $warehouses->isEmpty()) {
                continue;
            }

            // Find groundnut child categories
            $tira = $categories->firstWhere('category_code', 'GN-TIRA');
            $white = $categories->firstWhere('category_code', 'GN-WHITE');
            $others = $categories->firstWhere('category_code', 'GN-OTHER');

            // Use a generic brand placeholder if none exist
            $brandId = optional($tenant->brands()->first())->brand_id;

            $defs = [
                ['name' => 'Groundnuts Tira 1Kg', 'code' => 'GN-TIRA-1KG', 'cat' => $tira, 'weight' => 1.00],
                ['name' => 'Groundnuts Tira 500g', 'code' => 'GN-TIRA-500G', 'cat' => $tira, 'weight' => 0.50],
                ['name' => 'Groundnuts White 1Kg', 'code' => 'GN-WHITE-1KG', 'cat' => $white, 'weight' => 1.00],
                ['name' => 'Groundnuts White 500g', 'code' => 'GN-WHITE-500G', 'cat' => $white, 'weight' => 0.50],
                ['name' => 'Groundnuts (Others) 1Kg', 'code' => 'GN-OTHER-1KG', 'cat' => $others, 'weight' => 1.00],
            ];

            foreach ($defs as $idx => $d) {
                if (!$d['cat']) { continue; }

                $supplier = $suppliers->random();
                $shop = $shops->random();
                $warehouse = $warehouses->random();

                $costPrice = $faker->randomFloat(2, 1.2, 2.4) * (float)($d['weight'] * 1000) / 1000; // simple scale
                $sellingPrice = round($costPrice * $faker->randomFloat(2, 1.15, 1.5), 2);
                $stockQuantity = $faker->numberBetween(50, 500);

                Product::updateOrCreate(
                    [
                        'tenant_id' => $tenant->tenant_id,
                        'sku' => $d['code']
                    ],
                    [
                        'name' => $d['name'],
                        'description' => 'High quality groundnuts (' . $d['name'] . ')',
                        'short_description' => 'Premium selected ' . $d['name'],
                        'barcode' => $faker->ean13(),
                        'category_id' => $d['cat']->category_id,
                        'brand_id' => $brandId,
                        'supplier_id' => $supplier->supplier_id,
                        'shop_id' => $shop->shop_id,
                        'warehouse_id' => $warehouse->warehouse_id,
                        'cost_price' => $costPrice,
                        'selling_price' => $sellingPrice,
                        'discount_price' => null,
                        'tax_rate' => 0,
                        'stock_quantity' => $stockQuantity,
                        'min_stock_level' => 20,
                        'max_stock_level' => 1000,
                        'reorder_point' => 50,
                        'weight' => $d['weight'],
                        'dimensions_length' => 0,
                        'dimensions_width' => 0,
                        'dimensions_height' => 0,
                        'color' => null,
                        'size' => null,
                        'status' => 'active',
                        'is_featured' => $idx < 2,
                        'is_digital' => false,
                        'tags' => ['groundnuts', strtolower($d['name'])],
                        'meta_title' => $d['name'],
                        'meta_description' => 'Buy ' . $d['name'] . ' at best price',
                        'primary_image_url' => 'https://via.placeholder.com/400x400/8B4513/ffffff?text=' . urlencode($d['name']),
                        'gallery_images' => [],
                        'total_sold' => 0,
                        'total_revenue' => 0,
                        'last_sold_at' => null,
                    ]
                );
            }
        }

        $this->command->info('Created Groundnuts products (Tira, White, Others) for all tenants successfully.');
    }

}
