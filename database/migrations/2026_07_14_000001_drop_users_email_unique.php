<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop the unique constraint / index on users.email so multiple accounts
        // can share the same email (e.g. a shop owner using one email for
        // several staff accounts). Phone number is now the identifier.
        if (DB::connection()->getDriverName() === 'pgsql') {
            // In Postgres this was defined as a UNIQUE constraint (which owns
            // the underlying index) — DROP INDEX alone won't work.
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        } else {
            try {
                Schema::table('users', function ($table) {
                    $table->dropUnique('users_email_unique');
                });
            } catch (\Throwable $e) { /* index may not exist */ }
        }
    }

    public function down(): void
    {
        // Best-effort restore. If duplicates exist this will fail — that's OK,
        // it means someone already used the new freedom.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique ON users (email)');
        } else {
            Schema::table('users', function ($table) {
                $table->unique('email', 'users_email_unique');
            });
        }
    }
};
