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
        Schema::create('system_features', function (Blueprint $table) {
            $table->id('feature_id');
            $table->string('feature_key', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('default_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_features');
    }
};
