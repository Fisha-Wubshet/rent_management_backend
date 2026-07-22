<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When true, the user is forced to change their password before
            // they can use the app. Set on account creation (super admin
            // creates shop, shop admin creates staff/manager). Cleared once
            // the user picks their own password on first login.
            $table->boolean('must_change_password')->default(false)->after('recovery_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
