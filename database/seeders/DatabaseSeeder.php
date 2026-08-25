<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Structure only — the categories and page copy a fresh install needs to render
 * without blanks. No demo photos, posts or workshops: those are entered through
 * the dashboard.
 *
 * Create the first dashboard user with `php artisan make:filament-user`, then
 * grant it access with `php artisan admin:grant <email>`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DefaultCategoriesSeeder::class,
            PageSeeder::class,
        ]);
    }
}
