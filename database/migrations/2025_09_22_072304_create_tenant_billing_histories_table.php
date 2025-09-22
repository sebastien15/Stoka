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
        Schema::create('tenant_billing_histories', function (Blueprint $table) {
            $table->id('billing_history_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('invoice_number', 50)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_period', 50);
            $table->date('billing_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('payment_status', 30)->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'billing_date']);
            $table->index(['payment_status']);
            $table->index(['invoice_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_histories');
    }
};
