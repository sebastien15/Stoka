<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        // Drop composite unique only if it exists; restore legacy global unique only if no duplicates exist
        if ($this->indexExists('warehouses', 'warehouses_tenant_id_code_unique')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropUnique('warehouses_tenant_id_code_unique');
            });
        }

        if (!$this->columnHasDuplicates('warehouses', 'code')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique('code', 'warehouses_code_unique');
            });
        }

        if ($this->indexExists('shops', 'shops_tenant_id_code_unique')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropUnique('shops_tenant_id_code_unique');
            });
        }

        if (!$this->columnHasDuplicates('shops', 'code')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->unique('code', 'shops_code_unique');
            });
        }
    }

    /**
     * Return true if the given table has duplicate values for the column.
     */
    private function columnHasDuplicates(string $table, string $column): bool
    {
        $sql = "SELECT 1 FROM `{$table}` t
                GROUP BY t.`{$column}`
                HAVING COUNT(*) > 1
                LIMIT 1";
        $rows = DB::select($sql);
        return !empty($rows);
    }

    /**
     * Return true if an index with the given name exists on the table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);
        return !empty($rows);
    }
};
