<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->index('is_press_kit');
            $table->index('is_emergency_kit');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['is_press_kit']);
            $table->dropIndex(['is_emergency_kit']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
        });
    }
};
