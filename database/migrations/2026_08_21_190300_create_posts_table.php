<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('standfirst')->nullable();

            // Block array — `text` | `heading` | `figure` | `quote`. Maps 1:1 to a
            // Filament Builder field, so the shape the frontend renders is the shape
            // the dashboard edits.
            $table->json('body')->nullable();

            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('read_minutes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->date('published_on')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
