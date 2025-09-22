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
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id('config_id');
            $table->unsignedBigInteger('tenant_id')->nullable(); // null for global config
            $table->string('config_key', 100);
            $table->text('config_value');
            $table->string('data_type', 20)->default('string'); // string, integer, boolean, json
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'config_key']);
            $table->unique(['tenant_id', 'config_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};
