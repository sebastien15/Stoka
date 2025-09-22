<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tenant;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Idempotent seeding of Groundnuts categories for every tenant
            $parent = Category::firstOrCreate(
                [
                    'tenant_id' => $tenant->tenant_id,
                    'category_code' => 'GNUTS'
                ],
                [
                    'name' => 'Groundnuts',
                    'description' => 'All groundnut products',
                    'parent_category_id' => null,
                    'image_url' => 'https://via.placeholder.com/300x200/8B4513/ffffff?text=Groundnuts',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );

            $children = [
                ['code' => 'GN-TIRA', 'name' => 'Tira', 'order' => 1],
                ['code' => 'GN-WHITE', 'name' => 'White', 'order' => 2],
                ['code' => 'GN-OTHER', 'name' => 'Others', 'order' => 3],
            ];

            foreach ($children as $child) {
                Category::updateOrCreate(
                    [
                        'tenant_id' => $tenant->tenant_id,
                        'category_code' => $child['code']
                    ],
                    [
                        'name' => $child['name'],
                        'description' => $child['name'] . ' groundnuts',
                        'parent_category_id' => $parent->category_id,
                        'image_url' => 'https://via.placeholder.com/300x200/8B4513/ffffff?text=' . urlencode($child['name']),
                        'is_active' => true,
                        'sort_order' => $child['order'],
                    ]
                );
            }
        }

        $this->command->info('Created Groundnuts categories (Tira, White, Others) for all tenants successfully.');
    }
}
