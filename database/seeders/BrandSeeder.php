<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;
use App\Models\Tenant;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $brands = [];

            switch ($tenant->tenant_code) {
                case 'DEMO001': // TechWorld Electronics
                    $brands = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Apple',
                            'description' => 'Premium consumer electronics and computers',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=Apple',
                            'website_url' => 'https://apple.com',
                            'contact_email' => 'business@apple.com',
                            'contact_phone' => '+1-800-275-2273',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Samsung',
                            'description' => 'Electronics, smartphones, and appliances',
                            'logo_url' => 'https://via.placeholder.com/150x150/1428a0/ffffff?text=Samsung',
                            'website_url' => 'https://samsung.com',
                            'contact_email' => 'business@samsung.com',
                            'contact_phone' => '+1-800-726-7864',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Dell',
                            'description' => 'Computers, laptops, and enterprise solutions',
                            'logo_url' => 'https://via.placeholder.com/150x150/007db8/ffffff?text=Dell',
                            'website_url' => 'https://dell.com',
                            'contact_email' => 'business@dell.com',
                            'contact_phone' => '+1-800-289-3355',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'HP',
                            'description' => 'Computers, printers, and technology solutions',
                            'logo_url' => 'https://via.placeholder.com/150x150/0096d6/ffffff?text=HP',
                            'website_url' => 'https://hp.com',
                            'contact_email' => 'business@hp.com',
                            'contact_phone' => '+1-800-474-6836',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Sony',
                            'description' => 'Electronics, gaming, and entertainment',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=Sony',
                            'website_url' => 'https://sony.com',
                            'contact_email' => 'business@sony.com',
                            'contact_phone' => '+1-800-222-7669',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Microsoft',
                            'description' => 'Software, gaming, and computer hardware',
                            'logo_url' => 'https://via.placeholder.com/150x150/00bcf2/ffffff?text=Microsoft',
                            'website_url' => 'https://microsoft.com',
                            'contact_email' => 'business@microsoft.com',
                            'contact_phone' => '+1-800-642-7676',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Logitech',
                            'description' => 'Computer peripherals and accessories',
                            'logo_url' => 'https://via.placeholder.com/150x150/00b8fc/ffffff?text=Logitech',
                            'website_url' => 'https://logitech.com',
                            'contact_email' => 'business@logitech.com',
                            'contact_phone' => '+1-646-454-3200',
                            'is_active' => true,
                        ],
                    ];
                    break;

                case 'DEMO002': // Fashion Forward Boutique
                    $brands = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Calvin Klein',
                            'description' => 'Contemporary fashion and lifestyle brand',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=CK',
                            'website_url' => 'https://calvinklein.com',
                            'contact_email' => 'wholesale@calvinklein.com',
                            'contact_phone' => '+1-212-719-2600',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Tommy Hilfiger',
                            'description' => 'Classic American fashion with a modern twist',
                            'logo_url' => 'https://via.placeholder.com/150x150/c8102e/ffffff?text=TH',
                            'website_url' => 'https://tommyhilfiger.com',
                            'contact_email' => 'wholesale@tommyhilfiger.com',
                            'contact_phone' => '+1-212-223-1824',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Levi\'s',
                            'description' => 'Iconic denim and casual wear brand',
                            'logo_url' => 'https://via.placeholder.com/150x150/d22630/ffffff?text=Levis',
                            'website_url' => 'https://levi.com',
                            'contact_email' => 'wholesale@levi.com',
                            'contact_phone' => '+1-415-501-6000',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Nike',
                            'description' => 'Athletic footwear and sportswear',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=Nike',
                            'website_url' => 'https://nike.com',
                            'contact_email' => 'wholesale@nike.com',
                            'contact_phone' => '+1-503-671-6453',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Adidas',
                            'description' => 'Sports and lifestyle brand',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=Adidas',
                            'website_url' => 'https://adidas.com',
                            'contact_email' => 'wholesale@adidas.com',
                            'contact_phone' => '+1-971-234-2400',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Zara',
                            'description' => 'Fast fashion and trendy clothing',
                            'logo_url' => 'https://via.placeholder.com/150x150/000000/ffffff?text=Zara',
                            'website_url' => 'https://zara.com',
                            'contact_email' => 'wholesale@zara.com',
                            'contact_phone' => '+34-981-185-400',
                            'is_active' => true,
                        ],
                    ];
                    break;

                default: // Green Grocers and others
                    $brands = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Organic Valley',
                            'description' => 'Organic dairy and produce',
                            'logo_url' => 'https://via.placeholder.com/150x150/28a745/ffffff?text=Organic',
                            'website_url' => 'https://organicvalley.coop',
                            'contact_email' => 'info@organicvalley.coop',
                            'contact_phone' => '+1-608-625-2602',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fresh Express',
                            'description' => 'Fresh packaged salads and vegetables',
                            'logo_url' => 'https://via.placeholder.com/150x150/228b22/ffffff?text=Fresh',
                            'website_url' => 'https://freshexpress.com',
                            'contact_email' => 'info@freshexpress.com',
                            'contact_phone' => '+1-800-242-5472',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Coca-Cola',
                            'description' => 'Beverages and soft drinks',
                            'logo_url' => 'https://via.placeholder.com/150x150/ed1c16/ffffff?text=Coca+Cola',
                            'website_url' => 'https://coca-cola.com',
                            'contact_email' => 'business@coca-cola.com',
                            'contact_phone' => '+1-800-438-2653',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Nestle',
                            'description' => 'Food and beverage products',
                            'logo_url' => 'https://via.placeholder.com/150x150/ed1c24/ffffff?text=Nestle',
                            'website_url' => 'https://nestle.com',
                            'contact_email' => 'business@nestle.com',
                            'contact_phone' => '+1-800-225-2270',
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Local Farm Co.',
                            'description' => 'Local farm fresh products',
                            'logo_url' => 'https://via.placeholder.com/150x150/8b4513/ffffff?text=Local+Farm',
                            'website_url' => 'https://localfarmco.com',
                            'contact_email' => 'orders@localfarmco.com',
                            'contact_phone' => '+1-555-FARM-001',
                            'is_active' => true,
                        ],
                    ];
                    break;
            }

            foreach ($brands as $brandData) {
                Brand::create($brandData);
            }
        }

        $this->command->info('Created brands for all tenants successfully.');
    }
}