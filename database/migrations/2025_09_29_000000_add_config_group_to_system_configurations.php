<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_configurations')) {
            Schema::table('system_configurations', function (Blueprint $table) {
                if (!Schema::hasColumn('system_configurations', 'config_group')) {
                    $table->string('config_group', 50)->default('system')->after('tenant_id');
                }
            });

            // Backfill any existing rows to have a default group
            DB::table('system_configurations')
                ->whereNull('config_group')
                ->update(['config_group' => 'system']);

            // Adjust unique constraints to include config_group
            Schema::table('system_configurations', function (Blueprint $table) {
                // Drop old unique index if it exists
                try {
                    $table->dropUnique(['tenant_id', 'config_key']);
                } catch (\Throwable $e) {
                    // ignore if index name differs or doesn't exist
                }

                // Add new composite unique including config_group
                $table->unique(['tenant_id', 'config_group', 'config_key'], 'sc_tenant_group_key_unique');
                $table->index('config_group');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('system_configurations')) {
            Schema::table('system_configurations', function (Blueprint $table) {
                // Drop the new unique index
                try {
                    $table->dropUnique('sc_tenant_group_key_unique');
                } catch (\Throwable $e) {
                    // ignore
                }

                // Restore previous unique if needed
                try {
                    $table->unique(['tenant_id', 'config_key']);
                } catch (\Throwable $e) {
                    // ignore
                }

                if (Schema::hasColumn('system_configurations', 'config_group')) {
                    $table->dropColumn('config_group');
                }
            });
        }
    }
};







