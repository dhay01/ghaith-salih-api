<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The gallery filter bar and blog categories a fresh install needs in order to be
 * usable — without them the filter row is empty and photos cannot be filed.
 *
 * Deliberately carries no images and no demo photos: real content is entered
 * through the dashboard.
 */
class DefaultCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $gallery = ['Landscape', 'Panorama', 'Gigapixel', 'Portrait', 'Commercial', 'Architecture'];
        $blog = ['Behind the scenes', 'Tips', 'Gear', 'Tutorial', 'Craft', 'Journal'];

        foreach ($gallery as $i => $name) {
            $this->make(Category::TYPE_WORK, $name, $i + 1);
        }

        foreach ($blog as $i => $name) {
            $this->make(Category::TYPE_POST, $name, $i + 1);
        }
    }

    protected function make(string $type, string $name, int $position): void
    {
        Category::firstOrCreate(
            ['type' => $type, 'slug' => str($name)->slug()->toString()],
            ['name' => ['en' => $name], 'position' => $position],
        );
    }
}
