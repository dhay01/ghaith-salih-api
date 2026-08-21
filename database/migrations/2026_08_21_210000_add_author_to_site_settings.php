<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The byline on blog posts, previously hardcoded in src/data/posts.js. */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('author_name')->nullable();
            $table->json('author_location')->nullable();
            $table->json('author_bio')->nullable();
            $table->string('author_follow')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['author_name', 'author_location', 'author_bio', 'author_follow']);
        });
    }
};
