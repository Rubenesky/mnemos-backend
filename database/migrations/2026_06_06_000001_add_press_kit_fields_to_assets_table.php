<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_press_kit')->default(false)->after('is_public');
            $table->text('press_kit_description')->nullable()->after('is_press_kit');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['is_press_kit', 'press_kit_description']);
        });
    }
};
