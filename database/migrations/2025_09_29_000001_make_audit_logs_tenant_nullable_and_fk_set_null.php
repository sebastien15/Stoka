<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Make tenant_id nullable
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });

            Schema::table('audit_logs', function (Blueprint $table) {
                // Adjust foreign key to set null on tenant deletion
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable $e) {}

                $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable $e) {}
                // Revert to non-nullable and cascade
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
            });
        }
    }
};




