<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();

            // Translatable columns hold {"en": "...", "ar": "..."}.
            $table->json('title');
            $table->json('mode');
            $table->json('level');
            $table->json('location');
            $table->json('overview')->nullable();

            // Long-form detail; each is a list of translatable entries.
            $table->json('outcomes')->nullable();
            $table->json('syllabus')->nullable();
            $table->json('included')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('faqs')->nullable();

            // Money in minor units to avoid float drift.
            $table->unsignedInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->unsignedSmallInteger('seats_total')->default(10);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->boolean('is_published')->default(false);
            $table->boolean('accepts_reservations')->default(true);

            $table->timestamps();

            $table->index(['is_published', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};
