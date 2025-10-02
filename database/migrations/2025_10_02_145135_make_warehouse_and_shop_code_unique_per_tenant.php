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
        Schema::table('warehouses', function (Blueprint $table) {
            // Drop global unique on code (index name follows Laravel's convention)
            if (Schema::hasColumn('warehouses', 'code')) {
                try { $table->dropUnique('warehouses_code_unique'); } catch (\Throwable $e) {}
                $table->unique(['tenant_id', 'code'], 'warehouses_tenant_id_code_unique');
            }
        });

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'code')) {
                try { $table->dropUnique('shops_code_unique'); } catch (\Throwable $e) {}
                $table->unique(['tenant_id', 'code'], 'shops_tenant_id_code_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            try { $table->dropUnique('warehouses_tenant_id_code_unique'); } catch (\Throwable $e) {}
            $table->unique('code', 'warehouses_code_unique');
        });

        Schema::table('shops', function (Blueprint $table) {
            try { $table->dropUnique('shops_tenant_id_code_unique'); } catch (\Throwable $e) {}
            $table->unique('code', 'shops_code_unique');
        });
    }
};
