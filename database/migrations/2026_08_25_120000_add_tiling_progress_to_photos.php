<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Percentage reported by vips while it slices. Tiling a gigapixel original
     * runs for minutes, and a status that only says "Building…" for that long is
     * indistinguishable from one that has silently stalled.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedTinyInteger('dzi_progress')->nullable()->after('dzi_status');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('dzi_progress');
        });
    }
};
