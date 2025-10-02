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
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('customer_id');
            $table->string('phone_number', 20)->nullable()->after('tenant_id');
            $table->text('address')->nullable()->after('phone_number');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('country', 100)->nullable()->after('postal_code');
            $table->string('gender', 10)->nullable()->after('country');
            $table->string('preferred_language', 50)->nullable()->after('gender');
            $table->integer('total_orders')->default(0)->after('preferred_language');
            $table->decimal('total_spent', 10, 2)->default(0)->after('total_orders');
            $table->string('customer_tier', 20)->default('bronze')->after('total_spent');
            $table->boolean('marketing_consent')->default(false)->after('customer_tier');
            $table->string('preferred_contact_method', 50)->nullable()->after('marketing_consent');
            
            // Add foreign key constraint
            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
            
            // Add index
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn([
                'tenant_id',
                'phone_number',
                'address',
                'city',
                'state',
                'postal_code',
                'country',
                'gender',
                'preferred_language',
                'total_orders',
                'total_spent',
                'customer_tier',
                'marketing_consent',
                'preferred_contact_method'
            ]);
        });
    }
};
