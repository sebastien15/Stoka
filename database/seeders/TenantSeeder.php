<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'tenant_code' => 'DEMO001',
                'company_name' => 'TechWorld Electronics',
                'business_type' => 'Electronics Retail',
                'subscription_plan' => 'professional',
                'contact_person' => 'John Smith',
                'email' => 'admin@techworld.com',
                'phone_number' => '+1-555-0101',
                'website_url' => 'https://techworld.com',
                'address' => '123 Tech Street, Silicon Valley',
                'city' => 'Palo Alto',
                'state' => 'California',
                'postal_code' => '94301',
                'country' => 'United States',
                'timezone' => 'America/Los_Angeles',
                'tax_number' => 'US123456789',
                'registration_number' => 'REG001',
                'industry' => 'Electronics',
                'company_size' => 'medium',
                'subscription_start_date' => now()->subMonths(6),
                'subscription_end_date' => now()->addMonths(6),
                'billing_cycle' => 'monthly',
                'subscription_amount' => 99.99,
                'currency' => 'USD',
                'max_users' => 50,
                'max_products' => 10000,
                'max_warehouses' => 5,
                'max_shops' => 10,
                'storage_limit_gb' => 100,
                'api_requests_limit' => 100000,
                'status' => 'active',
                'is_trial' => false,
                'trial_days_remaining' => 0,
                'logo_url' => 'https://via.placeholder.com/200x100/007bff/ffffff?text=TechWorld',
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
                'backup_enabled' => true,
                'ssl_enabled' => true,
                'onboarding_completed' => true,
                'last_login_at' => now()->subDays(1),
            ],
            [
                'tenant_code' => 'DEMO002',
                'company_name' => 'Fashion Forward Boutique',
                'business_type' => 'Fashion Retail',
                'subscription_plan' => 'basic',
                'contact_person' => 'Sarah Johnson',
                'email' => 'contact@fashionforward.com',
                'phone_number' => '+1-555-0202',
                'website_url' => 'https://fashionforward.com',
                'address' => '456 Fashion Avenue, Manhattan',
                'city' => 'New York',
                'state' => 'New York',
                'postal_code' => '10001',
                'country' => 'United States',
                'timezone' => 'America/New_York',
                'tax_number' => 'US987654321',
                'registration_number' => 'REG002',
                'industry' => 'Fashion',
                'company_size' => 'small',
                'subscription_start_date' => now()->subMonths(3),
                'subscription_end_date' => now()->addMonths(9),
                'billing_cycle' => 'monthly',
                'subscription_amount' => 49.99,
                'currency' => 'USD',
                'max_users' => 20,
                'max_products' => 5000,
                'max_warehouses' => 2,
                'max_shops' => 5,
                'storage_limit_gb' => 50,
                'api_requests_limit' => 50000,
                'status' => 'active',
                'is_trial' => false,
                'trial_days_remaining' => 0,
                'logo_url' => 'https://via.placeholder.com/200x100/e83e8c/ffffff?text=Fashion+Forward',
                'primary_color' => '#e83e8c',
                'secondary_color' => '#6f42c1',
                'backup_enabled' => true,
                'ssl_enabled' => true,
                'onboarding_completed' => true,
                'last_login_at' => now()->subHours(6),
            ],
            [
                'tenant_code' => 'TRIAL001',
                'company_name' => 'Green Grocers Market',
                'business_type' => 'Grocery Store',
                'subscription_plan' => 'trial',
                'contact_person' => 'Mike Rodriguez',
                'email' => 'manager@greengrocers.com',
                'phone_number' => '+1-555-0303',
                'website_url' => 'https://greengrocers.com',
                'address' => '789 Market Street, Downtown',
                'city' => 'San Francisco',
                'state' => 'California',
                'postal_code' => '94102',
                'country' => 'United States',
                'timezone' => 'America/Los_Angeles',
                'tax_number' => 'US555123789',
                'registration_number' => 'REG003',
                'industry' => 'Food & Beverage',
                'company_size' => 'small',
                'subscription_start_date' => now()->subDays(15),
                'subscription_end_date' => now()->addDays(15),
                'billing_cycle' => 'monthly',
                'subscription_amount' => 0.00,
                'currency' => 'USD',
                'max_users' => 5,
                'max_products' => 1000,
                'max_warehouses' => 1,
                'max_shops' => 2,
                'storage_limit_gb' => 10,
                'api_requests_limit' => 10000,
                'status' => 'active',
                'is_trial' => true,
                'trial_days_remaining' => 15,
                'logo_url' => 'https://via.placeholder.com/200x100/28a745/ffffff?text=Green+Grocers',
                'primary_color' => '#28a745',
                'secondary_color' => '#20c997',
                'backup_enabled' => false,
                'ssl_enabled' => true,
                'onboarding_completed' => false,
                'last_login_at' => now()->subHours(2),
            ],
        ];

        foreach ($tenants as $tenantData) {
            Tenant::create($tenantData);
        }

        $this->command->info('Created ' . count($tenants) . ' tenants successfully.');
    }
}