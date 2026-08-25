<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Course pages show a duration like "2 days · 14 hrs". The day count is
     * derivable from the dates, but the contact hours are not, so it is authored.
     */
    public function up(): void
    {
        if (Schema::hasColumn('workshops', 'duration')) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table) {
            $table->json('duration')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
