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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id('warehouse_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->decimal('current_utilization', 5, 2)->default(0);
            $table->string('warehouse_type', 50)->default('storage');
            $table->boolean('is_active')->default(true);
            $table->string('operating_hours', 100)->nullable();
            $table->boolean('temperature_controlled')->default(false);
            $table->string('security_level', 20)->default('medium');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
