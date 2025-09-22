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
        Schema::table('categories', function (Blueprint $table) {
            // Drop global unique if exists, then add tenant-scoped unique
            try { $table->dropUnique('categories_category_code_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['tenant_id', 'category_code'], 'categories_tenant_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            try { $table->dropUnique('categories_tenant_code_unique'); } catch (\Throwable $e) {}
            $table->unique('category_code', 'categories_category_code_unique');
        });
    }
};
