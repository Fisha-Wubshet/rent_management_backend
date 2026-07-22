<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('country_code', 8)->nullable()->default('+251')->after('phone_number');
            $table->string('recovery_code_hash')->nullable()->after('password');
        });

        // Make email nullable so users can be created with only a phone number.
        // Wrap in try/catch: some drivers require doctrine/dbal for change().
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Fall back to raw SQL for PostgreSQL where change() may not be available.
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        }

        // Unique index on phone_number (only for non-null values).
        // Postgres supports partial index; MySQL falls back to non-unique index.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_phone_number_unique ON users (phone_number) WHERE phone_number IS NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->index('phone_number');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_phone_number_unique');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['phone_number']);
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'country_code', 'recovery_code_hash']);
        });
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
        }
    }
};
