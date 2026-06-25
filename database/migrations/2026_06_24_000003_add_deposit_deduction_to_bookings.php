<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_deduction', 10, 2)->nullable()->after('security_deposit_returned');
            $table->string('deposit_deduction_reason')->nullable()->after('deposit_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_deduction', 'deposit_deduction_reason']);
        });
    }
};
