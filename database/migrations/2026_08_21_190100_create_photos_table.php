<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gallery. The image file itself lives in the media library, not here —
     * this table carries only what the frontend needs to caption and lay it out.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->json('title');
            $table->json('location')->nullable();
            $table->json('gear')->nullable();
            $table->json('alt')->nullable();

            // e.g. "16/10" — drives the masonry cell before the image loads,
            // so it must be known server-side to avoid layout shift.
            $table->string('ratio', 12)->default('3/2');

            // Deep zoom: `is_zoomable` opts a photo into the lightbox's zoom UI,
            // `dzi_path` points at a vips-generated tile pyramid once one exists.
            $table->boolean('is_zoomable')->default(false);
            $table->string('dzi_path')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
