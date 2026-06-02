<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'cloudinary_public_id')) {
                $table->string('cloudinary_public_id')->nullable()->after('path');
            }
            if (!Schema::hasColumn('assets', 'cloudinary_url')) {
                $table->string('cloudinary_url')->nullable()->after('cloudinary_public_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['cloudinary_public_id', 'cloudinary_url']);
        });
    }
};