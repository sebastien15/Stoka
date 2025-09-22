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
        Schema::table('products', function (Blueprint $table) {
            try { $table->dropUnique('products_sku_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try { $table->dropUnique('products_tenant_sku_unique'); } catch (\Throwable $e) {}
            $table->unique('sku', 'products_sku_unique');
        });
    }
};
