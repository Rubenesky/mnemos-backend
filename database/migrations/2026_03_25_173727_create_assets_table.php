<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_name');        // nombre original del archivo
            $table->string('filename');             // nombre con el que se guarda en disco
            $table->string('mime_type');            // image/jpeg, application/pdf, etc.
            $table->unsignedBigInteger('size');     // tamaño en bytes
            $table->string('path');                 // ruta relativa en storage
            $table->enum('status', ['pending', 'processed', 'error'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};