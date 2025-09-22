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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id('profile_id');
            $table->unsignedBigInteger('customer_id'); // references users.user_id
            $table->string('company_name', 200)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country', 100)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 100)->nullable();
            $table->string('preferred_payment_method', 50)->nullable();
            $table->decimal('credit_limit', 10, 2)->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->date('date_of_birth')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
