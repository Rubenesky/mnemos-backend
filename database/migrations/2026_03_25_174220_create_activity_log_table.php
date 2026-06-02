<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action');                  // "upload", "delete", "edit", "login"
            $table->string('entity_type')->nullable(); // "Asset", "Category", etc.
            $table->unsignedBigInteger('entity_id')->nullable(); // id del elemento afectado
            $table->json('metadata')->nullable();      // datos extra si los necesitamos
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};