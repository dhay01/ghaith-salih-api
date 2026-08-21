<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table holding every word on the About page. The page was the
     * densest block of hardcoded prose in the app — 234 words across a template and
     * four script-level arrays.
     */
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table) {
            $table->id();

            $table->json('hero_title')->nullable();
            $table->json('hero_intro')->nullable();
            $table->json('disciplines')->nullable();

            $table->json('journey_title')->nullable();
            $table->json('journey_paragraphs')->nullable();
            $table->json('timeline')->nullable();

            $table->json('philosophy_quote')->nullable();
            $table->json('philosophy_note')->nullable();

            $table->json('approach')->nullable();

            $table->json('gear_title')->nullable();
            $table->json('gear')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }
};
