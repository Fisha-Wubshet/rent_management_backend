<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable so old rows survive the migration; new rows always set it.
            // nullOnDelete() so deleting a staff user doesn't cascade-nuke their bookings.
            $table->foreignId('created_by_id')->nullable()->after('branch_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['created_by_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['created_by_id']);
            $table->dropIndex(['created_by_id']);
            $table->dropColumn('created_by_id');
        });
    }
};
