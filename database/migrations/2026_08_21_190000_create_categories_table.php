<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gallery filters and blog categories are the same shape and are both editable
     * in the dashboard, so they share a table discriminated by `type` rather than
     * living as two near-identical tables — or, as before, as a hardcoded array in
     * the frontend.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('slug');
            $table->json('name');
            $table->unsignedSmallInteger('position')->default(0);

            // Layout hints for the home page's category showcase. Nullable because
            // blog categories don't use them.
            $table->unsignedTinyInteger('grid_span')->nullable();
            $table->string('grid_ratio', 12)->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
