<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiling happens in the background and can take minutes, so the dashboard
     * needs to be able to say what is happening rather than showing a photo that
     * silently is not zoomable yet.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('dzi_status')->nullable()->after('dzi_path');

            // Which uploaded file the current tiles were built from, so replacing
            // the image re-tiles and merely renaming the photo does not.
            $table->unsignedBigInteger('dzi_media_id')->nullable()->after('dzi_status');

            $table->text('dzi_error')->nullable()->after('dzi_media_id');
            $table->timestamp('dzi_generated_at')->nullable()->after('dzi_error');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['dzi_status', 'dzi_media_id', 'dzi_error', 'dzi_generated_at']);
        });
    }
};
