<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('booking_date');
            $table->date('return_date');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->string('alt_phone_number')->nullable();
            $table->string('booking_type')->default('CUSTOMER'); // CUSTOMER, MAINTENANCE
            $table->string('status')->default('CONFIRMED'); // CONFIRMED, PICKED_UP, RETURNED, CANCELLED
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_agreed_price', 10, 2)->default(0);
            $table->decimal('total_advance_payment', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->boolean('security_deposit_returned')->default(false);
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
