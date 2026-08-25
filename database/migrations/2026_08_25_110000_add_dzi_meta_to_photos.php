<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensions and tile geometry read out of the generated .dzi.
     *
     * Storing them lets the API hand the viewer a complete tile source, so the
     * browser never has to fetch the .dzi itself. That matters because the .dzi
     * would be an XHR — subject to CORS — while the tiles are plain <img>
     * requests that are not. Serving these as JSON sidesteps the whole problem
     * and means no web-server CORS configuration on deploy.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->json('dzi_meta')->nullable()->after('dzi_path');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('dzi_meta');
        });
    }
};
