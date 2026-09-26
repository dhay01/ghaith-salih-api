<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // A human sentence rather than an enum: tiling moves through several
            // phases of very different lengths, and the admin needs to read what
            // is happening right now ("Uploading tiles · 1,420 of 2,194"), not
            // decode a status word. Nothing branches on it.
            $table->string('dzi_stage')->nullable()->after('dzi_progress');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('dzi_stage');
        });
    }
};
