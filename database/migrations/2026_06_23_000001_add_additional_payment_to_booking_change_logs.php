<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_change_logs', function (Blueprint $table) {
            $table->decimal('additional_payment', 10, 2)->default(0)->after('new_advance_payment');
        });
    }

    public function down(): void
    {
        Schema::table('booking_change_logs', function (Blueprint $table) {
            $table->dropColumn('additional_payment');
        });
    }
};
