<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables Row-Level Security on every Laravel-managed table in the public
 * schema. Resolves Supabase "rls_disabled_in_public" and
 * "sensitive_columns_exposed" alerts on mnemos-production.
 *
 * No policies are created intentionally: with RLS on and zero policies,
 * direct REST API access (anon key) is denied for all rows. Laravel
 * connects as a Postgres role with BYPASSRLS (Supabase `postgres` or
 * equivalent), so application queries are unaffected.
 *
 * Guarded by driver so sqlite (CI, local test) is a no-op.
 */
return new class extends Migration
{
    /** Tables in public schema owned by this app, plus Laravel's migrations meta-table. */
    private const TABLES = [
        'activity_log',
        'ai_generations',
        'asset_category',
        'asset_metadata',
        'asset_views',
        'assets',
        'categories',
        'consents',
        'failed_jobs',
        'jobs',
        'migrations',
        'notifications',
        'organization_settings',
        'password_reset_tokens',
        'personal_access_tokens',
        'users',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE public.{$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
