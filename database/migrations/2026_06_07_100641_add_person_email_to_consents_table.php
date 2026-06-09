<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the nullable person_email column to the consents table.
 * Used for email notifications when sending consent request links.
 */
return new class extends Migration
{
    /**
     * Add nullable person_email column after person_name.
     */
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->string('person_email')->nullable()->after('person_name');
        });
    }

    /**
     * Drop the person_email column.
     */
    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn('person_email');
        });
    }
};
