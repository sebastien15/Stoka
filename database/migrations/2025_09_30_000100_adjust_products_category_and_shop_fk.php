<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            Schema::table('products', function (Blueprint $table) {
                try { $table->dropForeign(['category_id']); } catch (\Throwable $e) {}
                try { $table->dropForeign(['shop_id']); } catch (\Throwable $e) {}
            });

            Schema::table('products', function (Blueprint $table) {
                // Revert to NOT NULL (ensure no nulls exist before running down)
                $table->unsignedBigInteger('category_id')->nullable(false)->change();
                $table->unsignedBigInteger('shop_id')->nullable(false)->change();
            });

            Schema::table('products', function (Blueprint $table) {
                // Restore original restrictive FKs
                $table->foreign('category_id')
                    ->references('category_id')->on('categories')
                    ->onDelete('restrict');

                $table->foreign('shop_id')
                    ->references('shop_id')->on('shops')
                    ->onDelete('restrict');
            });
        }
    }
};
