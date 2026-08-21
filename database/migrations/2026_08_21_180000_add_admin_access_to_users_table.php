<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `/admin` is the only authenticated surface in this app and it exposes every
     * applicant's name, phone and email, so panel access is opt-in per user rather
     * than implied by simply having a row in `users`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');

            // Filament's app-authentication provider stores these itself; both are
            // encrypted at the cast level, hence text rather than string.
            $table->text('app_authentication_secret')->nullable()->after('is_admin');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'app_authentication_secret',
                'app_authentication_recovery_codes',
            ]);
        });
    }
};
