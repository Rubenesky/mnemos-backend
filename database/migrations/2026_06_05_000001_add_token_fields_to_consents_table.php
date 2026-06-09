<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('notes');
            $table->dateTime('token_expires_at')->nullable()->after('token');
            $table->dateTime('responded_at')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn(['token', 'token_expires_at', 'responded_at']);
        });
    }
};
