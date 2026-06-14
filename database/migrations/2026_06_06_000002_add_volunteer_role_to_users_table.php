<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','editor','viewer','volunteer') NOT NULL DEFAULT 'viewer'");
        }

        if ($driver === 'sqlite') {
            // SQLite stores enum as TEXT with a CHECK constraint.
            // Drop the column and recreate with the extended value list.
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'editor', 'viewer', 'volunteer'])
                    ->default('viewer')
                    ->after('email');
            });
        }

        // PostgreSQL: Laravel creates enums as VARCHAR + CHECK constraint.
        // Drop the existing constraint and recreate it with the extended value list.
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','editor','viewer','volunteer'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer'");
        }

        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'editor', 'viewer'])
                    ->default('viewer')
                    ->after('email');
            });
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','editor','viewer'))");
        }
    }
};
