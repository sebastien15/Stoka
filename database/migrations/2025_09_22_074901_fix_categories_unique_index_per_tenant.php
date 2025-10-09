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
        // Drop composite unique only if it exists
        if ($this->indexExists('categories', 'categories_tenant_code_unique')) {
            // Attempt to drop composite unique; if required by an FK, skip to allow rollback
            try {
                Schema::table('categories', function (Blueprint $table) {
                    $table->dropUnique('categories_tenant_code_unique');
                });
            } catch (\Throwable $e) {
                // Swallow error so rollback can proceed
            }
        }

        // Restore legacy global unique only if no duplicates exist
        if (!$this->columnHasDuplicates('categories', 'category_code')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unique('category_code', 'categories_category_code_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);
        return !empty($rows);
    }

    private function columnHasDuplicates(string $table, string $column): bool
    {
        $sql = "SELECT 1 FROM `{$table}` t GROUP BY t.`{$column}` HAVING COUNT(*) > 1 LIMIT 1";
        $rows = DB::select($sql);
        return !empty($rows);
    }
};
