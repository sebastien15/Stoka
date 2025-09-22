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
        Schema::create('products', function (Blueprint $table) {
            $table->id('product_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->string('qr_code', 100)->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('cost_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_level')->nullable();
            $table->integer('max_stock_level')->nullable();
            $table->integer('reorder_point')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('dimensions_length', 8, 2)->nullable();
            $table->decimal('dimensions_width', 8, 2)->nullable();
            $table->decimal('dimensions_height', 8, 2)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->json('tags')->nullable();
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('primary_image_url', 500)->nullable();
            $table->json('gallery_images')->nullable();
            $table->integer('total_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamp('last_sold_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
