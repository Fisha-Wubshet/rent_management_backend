<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_change_logs', function (Blueprint $table) {
            $table->string('date_change_summary')->nullable()->after('additional_payment');
        });
    }

    public function down(): void
    {
        Schema::table('booking_change_logs', function (Blueprint $table) {
            $table->dropColumn('date_change_summary');
        });
    }
};
