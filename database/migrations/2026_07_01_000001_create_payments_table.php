<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // ADVANCE, ADDITIONAL, SECURITY_DEPOSIT, DEPOSIT_RETURNED,
            // DEPOSIT_DEDUCTED, DAMAGE_CHARGE, REFUND
            $table->string('type', 30);
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('recorded_by_id')->nullable();
            $table->string('recorded_by_name');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
