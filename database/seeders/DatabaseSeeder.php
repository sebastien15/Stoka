<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // Run seeders in correct order (respecting foreign key constraints)
        $this->call([
            TenantSeeder::class,
            WarehouseSeeder::class,
            ShopSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            // OrderSeeder::class,
            // PurchaseSeeder::class,
            // ExpenseSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info('• Tenants: 3 (Demo companies + Trial)');
        $this->command->info('• Users: Multiple roles per tenant');
        $this->command->info('• Warehouses & Shops: Business locations');
        $this->command->info('• Categories & Brands: Product organization');
        $this->command->info('• Suppliers: Vendor relationships');
        $this->command->info('• Products: Sample inventory');
        $this->command->newLine();
        $this->command->info('🔐 Default login credentials:');
        $this->command->info('• TechWorld: admin@techworld.com / password123');
        $this->command->info('• Fashion Forward: contact@fashionforward.com / password123');
        $this->command->info('• Green Grocers: manager@greengrocers.com / password123');
    }
}
