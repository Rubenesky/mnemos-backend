<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the ai_generations table.
 *
 * Each row records a single AI generation event for an asset, enabling
 * a full audit trail of every AI call made against that asset.
 * Only created_at is stored — these records are immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->enum('generation_type', ['alt_text', 'tags', 'description', 'report', 'story']);
            $table->string('model', 50);
            $table->text('prompt_summary');
            $table->text('response_preview');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Soft FK — keep generation log even if the triggering user is removed
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
