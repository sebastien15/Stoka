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
        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id('tenant_feature_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('feature_id');
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('enabled_by');
            $table->timestamp('enabled_at');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'feature_id']);
            $table->unique(['tenant_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
    }
};
