<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop existing foreign keys if they exist
                try { $table->dropForeign(['category_id']); } catch (\Throwable $e) {}
                try { $table->dropForeign(['shop_id']); } catch (\Throwable $e) {}
            });

            Schema::table('products', function (Blueprint $table) {
                // Make columns nullable to allow SET NULL behavior
                $table->unsignedBigInteger('category_id')->nullable()->change();
                $table->unsignedBigInteger('shop_id')->nullable()->change();
            });

            Schema::table('products', function (Blueprint $table) {
                // Recreate foreign keys with ON DELETE SET NULL
                $table->foreign('category_id')
                    ->references('category_id')->on('categories')
                    ->onDelete('set null');

                $table->foreign('shop_id')
                    ->references('shop_id')->on('shops')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            // Drop FKs if they exist by actual constraint names
            $this->dropForeignKeyIfExists('products', 'category_id');
            $this->dropForeignKeyIfExists('products', 'shop_id');

            // Leave columns nullable to avoid invalid use of NULL during rollback

            Schema::table('products', function (Blueprint $table) {
                // Restore restrictive FKs while allowing nullable columns
                $table->foreign('category_id')
                    ->references('category_id')->on('categories')
                    ->onDelete('restrict');

                $table->foreign('shop_id')
                    ->references('shop_id')->on('shops')
                    ->onDelete('restrict');
            });
        }
    }

    private function dropForeignKeyIfExists(string $tableName, string $columnName): void
    {
        $databaseName = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL 
             LIMIT 1',
            [$databaseName, $tableName, $columnName]
        );

        if (!empty($rows)) {
            $constraintName = $rows[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};
