<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Tenant;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $suppliers = [];

            switch ($tenant->tenant_code) {
                case 'DEMO001': // TechWorld Electronics
                    $suppliers = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Tech Distributors Inc.',
                            'contact_person' => 'Michael Thompson',
                            'email' => 'orders@techdist.com',
                            'phone_number' => '+1-555-8001',
                            'address' => '1500 Tech Park Drive, Silicon Valley, CA',
                            'city' => 'San Jose',
                            'country' => 'United States',
                            'tax_number' => 'TAX-TD-001',
                            'payment_terms' => 'Net 30',
                            'credit_limit' => 50000.00,
                            'rating' => 4.5,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Global Electronics Supply',
                            'contact_person' => 'Jennifer Wu',
                            'email' => 'supply@globalelec.com',
                            'phone_number' => '+1-555-8002',
                            'address' => '2200 Commerce Blvd, Fremont, CA',
                            'city' => 'Fremont',
                            'country' => 'United States',
                            'tax_number' => 'TAX-GES-002',
                            'payment_terms' => 'Net 45',
                            'credit_limit' => 75000.00,
                            'rating' => 4.2,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Asian Tech Imports',
                            'contact_person' => 'David Kim',
                            'email' => 'imports@asiantech.com',
                            'phone_number' => '+1-555-8003',
                            'address' => '800 International Way, Los Angeles, CA',
                            'city' => 'Los Angeles',
                            'country' => 'United States',
                            'tax_number' => 'TAX-ATI-003',
                            'payment_terms' => 'Net 60',
                            'credit_limit' => 100000.00,
                            'rating' => 4.8,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Component Solutions LLC',
                            'contact_person' => 'Sarah Davis',
                            'email' => 'sales@compsolutions.com',
                            'phone_number' => '+1-555-8004',
                            'address' => '1200 Industrial Park, Austin, TX',
                            'city' => 'Austin',
                            'country' => 'United States',
                            'tax_number' => 'TAX-CSL-004',
                            'payment_terms' => 'Net 30',
                            'credit_limit' => 30000.00,
                            'rating' => 4.0,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Euro Tech Distribution',
                            'contact_person' => 'Hans Mueller',
                            'email' => 'distribution@eurotech.de',
                            'phone_number' => '+49-89-123-4567',
                            'address' => 'Maximilianstrasse 25, Munich, Germany',
                            'city' => 'Munich',
                            'country' => 'Germany',
                            'tax_number' => 'DE-TAX-ETD-005',
                            'payment_terms' => 'Net 45',
                            'credit_limit' => 60000.00,
                            'rating' => 4.3,
                            'is_active' => true,
                        ],
                    ];
                    break;

                case 'DEMO002': // Fashion Forward Boutique
                    $suppliers = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fashion Wholesale NYC',
                            'contact_person' => 'Maria Rodriguez',
                            'email' => 'wholesale@fashionnyc.com',
                            'phone_number' => '+1-555-9001',
                            'address' => '350 Fashion Avenue, New York, NY',
                            'city' => 'New York',
                            'country' => 'United States',
                            'tax_number' => 'TAX-FW-001',
                            'payment_terms' => 'Net 30',
                            'credit_limit' => 25000.00,
                            'rating' => 4.4,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'European Fashion House',
                            'contact_person' => 'Isabella Rossi',
                            'email' => 'orders@europeanfashion.it',
                            'phone_number' => '+39-02-123-4567',
                            'address' => 'Via della Moda 15, Milan, Italy',
                            'city' => 'Milan',
                            'country' => 'Italy',
                            'tax_number' => 'IT-TAX-EFH-002',
                            'payment_terms' => 'Net 45',
                            'credit_limit' => 40000.00,
                            'rating' => 4.7,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Textile Suppliers Co.',
                            'contact_person' => 'John Anderson',
                            'email' => 'supply@textileco.com',
                            'phone_number' => '+1-555-9003',
                            'address' => '1800 Textile Row, Charlotte, NC',
                            'city' => 'Charlotte',
                            'country' => 'United States',
                            'tax_number' => 'TAX-TSC-003',
                            'payment_terms' => 'Net 60',
                            'credit_limit' => 35000.00,
                            'rating' => 4.1,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Asian Fashion Imports',
                            'contact_person' => 'Li Chen',
                            'email' => 'imports@asianfashion.com',
                            'phone_number' => '+86-21-1234-5678',
                            'address' => '500 Fashion District, Shanghai, China',
                            'city' => 'Shanghai',
                            'country' => 'China',
                            'tax_number' => 'CN-TAX-AFI-004',
                            'payment_terms' => 'Net 30',
                            'credit_limit' => 50000.00,
                            'rating' => 4.6,
                            'is_active' => true,
                        ],
                    ];
                    break;

                default: // Green Grocers and others
                    $suppliers = [
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Fresh Farm Produce',
                            'contact_person' => 'Robert Green',
                            'email' => 'orders@freshfarm.com',
                            'phone_number' => '+1-555-7001',
                            'address' => '2500 Farm Road, Salinas, CA',
                            'city' => 'Salinas',
                            'country' => 'United States',
                            'tax_number' => 'TAX-FFP-001',
                            'payment_terms' => 'Net 15',
                            'credit_limit' => 15000.00,
                            'rating' => 4.5,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Organic Valley Distributors',
                            'contact_person' => 'Emily Johnson',
                            'email' => 'distribution@organicvalley.com',
                            'phone_number' => '+1-555-7002',
                            'address' => '1200 Organic Lane, Petaluma, CA',
                            'city' => 'Petaluma',
                            'country' => 'United States',
                            'tax_number' => 'TAX-OVD-002',
                            'payment_terms' => 'Net 30',
                            'credit_limit' => 20000.00,
                            'rating' => 4.8,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Pacific Seafood Supply',
                            'contact_person' => 'Captain Mark Fisher',
                            'email' => 'supply@pacificseafood.com',
                            'phone_number' => '+1-555-7003',
                            'address' => '800 Harbor Drive, Monterey, CA',
                            'city' => 'Monterey',
                            'country' => 'United States',
                            'tax_number' => 'TAX-PSS-003',
                            'payment_terms' => 'Net 7',
                            'credit_limit' => 10000.00,
                            'rating' => 4.2,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Golden State Dairy',
                            'contact_person' => 'Linda Miller',
                            'email' => 'orders@goldenstate.com',
                            'phone_number' => '+1-555-7004',
                            'address' => '3000 Dairy Farm Road, Modesto, CA',
                            'city' => 'Modesto',
                            'country' => 'United States',
                            'tax_number' => 'TAX-GSD-004',
                            'payment_terms' => 'Net 14',
                            'credit_limit' => 12000.00,
                            'rating' => 4.4,
                            'is_active' => true,
                        ],
                        [
                            'tenant_id' => $tenant->tenant_id,
                            'name' => 'Local Beverage Distributors',
                            'contact_person' => 'Carlos Martinez',
                            'email' => 'orders@localbev.com',
                            'phone_number' => '+1-555-7005',
                            'address' => '1500 Distribution Center, San Francisco, CA',
                            'city' => 'San Francisco',
                            'country' => 'United States',
                            'tax_number' => 'TAX-LBD-005',
                            'payment_terms' => 'Net 21',
                            'credit_limit' => 8000.00,
                            'rating' => 4.0,
                            'is_active' => true,
                        ],
                    ];
                    break;
            }

            foreach ($suppliers as $supplierData) {
                Supplier::create($supplierData);
            }
        }

        $this->command->info('Created suppliers for all tenants successfully.');
    }
}