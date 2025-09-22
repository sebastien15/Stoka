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
        Schema::create('notice_events', function (Blueprint $table) {
            $table->id('notice_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('title', 200);
            $table->text('message');
            $table->string('type', 50)->default('info'); // info, warning, error, success
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->boolean('is_read')->default(false);
            $table->boolean('is_global')->default(false);
            $table->json('target_users')->nullable(); // array of user IDs
            $table->json('target_roles')->nullable(); // array of roles
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'is_read']);
            $table->index(['type']);
            $table->index(['priority']);
            $table->index(['scheduled_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_events');
    }
};
