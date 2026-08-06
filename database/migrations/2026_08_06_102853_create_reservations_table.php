<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();

            // Identity lives in real columns: these are filtered, sorted,
            // deduped and exported, so they must not be buried in JSON.
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 16)->nullable();

            $table->unsignedTinyInteger('seats')->default(1);

            // Questionnaire responses. Kept as JSON with the version of the
            // question set that produced them, so editing the form later does
            // not invalidate or silently reinterpret older submissions.
            $table->json('answers');
            $table->string('question_set_version', 32)->default('v1');

            $table->string('status', 16)->default('pending');
            $table->string('locale', 5)->default('en');

            // Abuse tracing for a public, unauthenticated endpoint.
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['workshop_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
