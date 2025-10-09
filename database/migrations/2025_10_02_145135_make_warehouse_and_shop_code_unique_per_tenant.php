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
            // Attempt to drop composite unique; if MySQL reports it's required by an FK (1553), skip dropping
            try {
                Schema::table('warehouses', function (Blueprint $table) {
                    $table->dropUnique('warehouses_tenant_id_code_unique');
                });
            } catch (\Throwable $e) {
                // Swallow error to allow rollback to proceed when the index is required by an FK
            }
        }

        if (!$this->columnHasDuplicates('warehouses', 'code')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique('code', 'warehouses_code_unique');
            });
        }

        if ($this->indexExists('shops', 'shops_tenant_id_code_unique')) {
            // Attempt to drop composite unique; if required by an FK, skip dropping to allow rollback
            try {
                Schema::table('shops', function (Blueprint $table) {
                    $table->dropUnique('shops_tenant_id_code_unique');
                });
            } catch (\Throwable $e) {
                // Swallow error to proceed
            }
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

    /**
     * Return true if any foreign key references the given table using the specified referenced columns
     * in the given order. Guards against dropping an index required by an FK (MySQL error 1553).
     */
    private function indexIsReferencedByForeignKey(string $referencedTable, string ...$referencedColumns): bool
    {
        if (empty($referencedColumns)) {
            return false;
        }

        $dbName = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT kcu.CONSTRAINT_NAME, kcu.REFERENCED_COLUMN_NAME, kcu.ORDINAL_POSITION
             FROM information_schema.KEY_COLUMN_USAGE kcu
             WHERE kcu.CONSTRAINT_SCHEMA = ?
               AND kcu.REFERENCED_TABLE_NAME = ?
             ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            [$dbName, $referencedTable]
        );

        if (empty($rows)) {
            return false;
        }

        $byConstraint = [];
        foreach ($rows as $row) {
            $byConstraint[$row->CONSTRAINT_NAME][] = $row->REFERENCED_COLUMN_NAME;
        }

        foreach ($byConstraint as $cols) {
            if (count($cols) !== count($referencedColumns)) {
                continue;
            }
            $matches = true;
            foreach (array_values($referencedColumns) as $i => $col) {
                if (strcasecmp($cols[$i] ?? '', $col) !== 0) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }
};
