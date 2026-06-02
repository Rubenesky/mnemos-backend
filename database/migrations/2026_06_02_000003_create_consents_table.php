<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->string('person_name');           // Name of the person who gave/denied consent
            $table->date('consent_date');             // Date consent was requested/obtained
            $table->string('consent_type');          // 'photo', 'video', 'audio', 'general'
            $table->enum('status', ['obtained', 'pending', 'denied'])->default('pending');
            $table->string('document_path')->nullable(); // Path to signed consent form (uploaded doc)
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
