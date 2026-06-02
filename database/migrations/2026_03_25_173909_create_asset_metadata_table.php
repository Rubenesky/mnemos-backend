<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();           // título generado por IA o editado por usuario
            $table->text('description')->nullable();       // descripción generada por IA o editada
            $table->json('tags')->nullable();              // array de etiquetas ["diseño", "logo", "web"]
            $table->boolean('ai_generated')->default(false); // indica si lo generó la IA
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_metadata');
    }
};