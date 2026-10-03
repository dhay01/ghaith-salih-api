<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Images uploaded before shapes were measured on upload. Runs once on deploy;
     * the command skips any file it cannot read rather than failing the deploy.
     */
    public function up(): void
    {
        Artisan::call('media:remember-shapes');
    }

    public function down(): void
    {
        //
    }
};
