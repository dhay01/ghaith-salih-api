<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields the course pages needed but were reading from a hardcoded frontend
     * module: the cover image (via the media library), the human-readable duration,
     * and the attendance note the archive shows for past workshops.
     */
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->json('duration')->nullable()->after('level');
            $table->json('attendees')->nullable()->after('seats_total');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn(['duration', 'attendees']);
        });
    }
};
