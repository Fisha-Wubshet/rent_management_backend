<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // 'operational' (default, 90-day retention) or 'critical' (kept forever).
            $table->string('retention', 15)->default('operational')->after('action');
            $table->index(['retention', 'timestamp']);   // makes the nightly prune fast
        });

        // Backfill existing rows using the same classification the service will
        // apply going forward. Anything not listed defaults to 'operational'.
        $critical = [
            'LOGIN_FAILED',
            'PASSWORD_RESET',
            'PASSWORD_RESET_FAILED',
            'FIRST_LOGIN_PASSWORD_SET',
            'RECOVERY_CODE_REGENERATED',
            'RECOVERY_CODE_REGENERATE_FAILED',
            'PAYMENT_RECORDED',
            'DELETED',
            'CANCELLED',
            'BLACKLISTED',
            'UNBLACKLISTED',
            'STAFF_UPDATED',
        ];

        DB::table('audit_logs')
            ->whereIn('action', $critical)
            ->update(['retention' => 'critical']);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['retention', 'timestamp']);
            $table->dropColumn('retention');
        });
    }
};
