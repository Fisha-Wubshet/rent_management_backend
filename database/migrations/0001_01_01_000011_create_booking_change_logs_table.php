<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->text('removed_items_summary')->nullable();
            $table->text('added_items_summary')->nullable();
            $table->decimal('old_total_price', 10, 2)->nullable();
            $table->decimal('new_total_price', 10, 2)->nullable();
            $table->decimal('new_advance_payment', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_logs');
    }
};
