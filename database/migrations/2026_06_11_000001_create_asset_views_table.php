<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the asset_views table for anonymous view tracking.
 *
 * IPs are stored as SHA-256 hashes to comply with GDPR —
 * the original IP is never persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->string('ip_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_views');
    }
};
