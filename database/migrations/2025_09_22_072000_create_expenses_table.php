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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id('expense_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('expense_number', 50)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('receipt_url', 500)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'category']);
            $table->index(['expense_number']);
            $table->index(['status']);
            $table->index(['expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
