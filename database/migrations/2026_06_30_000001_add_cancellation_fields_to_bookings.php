<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('refund_amount', 10, 2)->default(0)->after('excess_damage_charge');
            $table->string('cancellation_reason', 500)->nullable()->after('refund_amount');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'cancellation_reason', 'cancelled_at']);
        });
    }
};
