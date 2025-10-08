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
        // Drop composite unique only if it exists and is not required by any FK
        if ($this->indexExists('products', 'products_tenant_sku_unique') &&
            !$this->isIndexRequiredByForeignKey('products', 'products_tenant_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_tenant_sku_unique');
            });
        }

        // Restore legacy global unique on sku only if no duplicates exist
        if (!$this->columnHasDuplicates('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('sku', 'products_sku_unique');
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

    private function isIndexRequiredByForeignKey(string $tableName, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        // Determine columns for given index in order
        $indexRows = DB::select('SHOW INDEX FROM ' . $tableName . ' WHERE Key_name = ?', [$indexName]);
        if (empty($indexRows)) {
            return false;
        }
        $indexColumnsBySeq = [];
        foreach ($indexRows as $row) {
            $indexColumnsBySeq[(int)($row->Seq_in_index ?? 0)] = $row->Column_name;
        }
        ksort($indexColumnsBySeq);
        $indexedColumns = array_values($indexColumnsBySeq);

        // All FK columns on this table
        $fkRows = DB::select(
            'SELECT CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
            [$databaseName, $tableName]
        );
        if (empty($fkRows)) {
            return false;
        }
        $fkColumnsByConstraint = [];
        foreach ($fkRows as $row) {
            $constraint = $row->CONSTRAINT_NAME;
            $position = (int)$row->ORDINAL_POSITION;
            if (!isset($fkColumnsByConstraint[$constraint])) {
                $fkColumnsByConstraint[$constraint] = [];
            }
            $fkColumnsByConstraint[$constraint][$position] = $row->COLUMN_NAME;
        }
        foreach ($fkColumnsByConstraint as $colsByPos) {
            ksort($colsByPos);
            $fkColumns = array_values($colsByPos);
            $prefix = array_slice($indexedColumns, 0, count($fkColumns));
            if ($fkColumns === $prefix) {
                return true;
            }
        }

        return false;
    }
};
