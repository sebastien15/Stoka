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
        // Update all super admin users to have tenant_id = null
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['tenant_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get the first tenant to assign super admins back to it
        $firstTenant = DB::table('tenants')->first();
        
        if ($firstTenant) {
            DB::table('users')
                ->where('role', 'super_admin')
                ->update(['tenant_id' => $firstTenant->tenant_id]);
        }
    }
};
