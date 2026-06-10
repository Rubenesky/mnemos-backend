<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds EU AI Act provenance columns to the assets table.
 *
 * These columns enable Mnemos to demonstrate traceability of AI-generated
 * content as required by the EU AI Act (deadline August 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('ai_model', 50)->nullable()->after('extracted_text');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_model');
            $table->text('ai_prompt')->nullable()->after('ai_generated_at');
            $table->unsignedBigInteger('ai_reviewed_by')->nullable()->after('ai_prompt');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_reviewed_by');

            // Soft FK — intentionally nullOnDelete so review history
            // survives if the reviewing user is later deleted.
            $table->foreign('ai_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['ai_reviewed_by']);
            $table->dropColumn([
                'ai_model',
                'ai_generated_at',
                'ai_prompt',
                'ai_reviewed_by',
                'ai_reviewed_at',
            ]);
        });
    }
};
