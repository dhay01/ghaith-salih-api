<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-route copy: the eyebrow/headline/intro every page opens with, plus any
     * named sections that page renders. One row per route keyed by `key`, so the
     * frontend asks for `pages.blog` instead of hardcoding "notes from the field".
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            $table->json('eyebrow')->nullable();
            $table->json('title')->nullable();
            $table->json('intro')->nullable();

            // [{ key, eyebrow, heading, body, note }] — looked up by `key` in the
            // template, so adding a section needs a renderer, but editing one does not.
            $table->json('sections')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
