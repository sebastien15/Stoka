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
        Schema::create('shops', function (Blueprint $table) {
            $table->id('shop_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses', 'warehouse_id')->onDelete('set null');
            $table->string('shop_type', 50)->default('retail');
            $table->decimal('floor_area', 10, 2)->nullable();
            $table->decimal('rent_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('opening_hours', 100)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->json('social_media_handles')->nullable();
            $table->string('pos_system', 100)->nullable();
            $table->boolean('online_shop_enabled')->default(false);
            $table->boolean('delivery_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
