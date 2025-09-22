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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id('purchase_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('purchase_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 30)->default('pending');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->string('payment_status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['purchase_number']);
            $table->index(['status']);
            $table->index(['order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
