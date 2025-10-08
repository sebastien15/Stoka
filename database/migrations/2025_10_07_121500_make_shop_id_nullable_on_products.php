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
            // Requires doctrine/dbal to modify existing column
            $table->unsignedBigInteger('shop_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop FK by actual name if it exists (Blueprint dropForeign cannot be conditionally guarded)
        $this->dropForeignKeyIfExists('products', 'shop_id');

        Schema::table('products', function (Blueprint $table) {
            // Recreate FK with restrictive behavior; column may remain nullable here
            $table->foreign('shop_id')
                ->references('shop_id')->on('shops')
                ->onDelete('restrict');
        });
    }

    /**
     * Drop a foreign key on the given table/column if it exists, using information_schema to find the constraint name.
     */
    private function dropForeignKeyIfExists(string $tableName, string $columnName): void
    {
        $databaseName = \Illuminate\Support\Facades\DB::getDatabaseName();
        $rows = \Illuminate\Support\Facades\DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL 
             LIMIT 1',
            [$databaseName, $tableName, $columnName]
        );

        if (!empty($rows)) {
            $constraintName = $rows[0]->CONSTRAINT_NAME;
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};


