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
        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 30)->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_method', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('order_date');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['order_number']);
            $table->index(['status']);
            $table->index(['order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
