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
        Schema::table('order_items', function (Blueprint $table) {
            // Add tenant_id column for multi-tenancy
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', 'tenant_id')->onDelete('cascade');
            
            // Add variant_id column for product variants
            $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
            
            // Add tax_amount column for tax calculations
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
            
            // Add indexes for better performance
            $table->index(['tenant_id']);
            $table->index(['variant_id']);
            
            // Add foreign key constraint for variant_id
            $table->foreign('variant_id')->references('variant_id')->on('product_variants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['variant_id']);
            
            // Drop indexes
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['variant_id']);
            
            // Drop columns
            $table->dropColumn(['tenant_id', 'variant_id', 'tax_amount']);
        });
    }
};
