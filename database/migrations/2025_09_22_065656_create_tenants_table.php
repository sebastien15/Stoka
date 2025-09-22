<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id('tenant_id');
            $table->string('tenant_code', 20)->unique();
            $table->string('company_name', 200);
            $table->string('business_type', 100)->nullable();
            $table->string('subscription_plan', 50)->default('trial');
            $table->string('contact_person', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->string('tax_number', 50)->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 20)->default('small');
            $table->date('subscription_start_date')->nullable();
            $table->date('subscription_end_date')->nullable();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->decimal('subscription_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->integer('max_users')->default(5);
            $table->integer('max_products')->default(1000);
            $table->integer('max_warehouses')->default(1);
            $table->integer('max_shops')->default(2);
            $table->integer('storage_limit_gb')->default(10);
            $table->integer('api_requests_limit')->default(10000);
            $table->string('status', 20)->default('active');
            $table->boolean('is_trial')->default(true);
            $table->integer('trial_days_remaining')->default(30);
            $table->string('logo_url', 500)->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('custom_domain', 200)->nullable();
            $table->string('database_schema', 100)->nullable();
            $table->string('cdn_url', 500)->nullable();
            $table->boolean('backup_enabled')->default(false);
            $table->boolean('ssl_enabled')->default(true);
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
