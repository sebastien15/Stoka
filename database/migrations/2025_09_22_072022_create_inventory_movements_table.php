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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id('movement_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('movement_type', 30); // in, out, adjustment
            $table->integer('quantity_before');
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reference_type', 50)->nullable(); // order, purchase, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason', 255);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'product_id']);
            $table->index(['movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
