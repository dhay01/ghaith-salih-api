<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photographs are not ordered by hand, so the archive comes newest first, and a
 * category without a cover of its own shows its newest work.
 */
class PhotoOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
        Storage::fake(config('gigapixel.disk'));
    }

    protected function jpeg(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'shape').'.jpg';
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    protected function photo(string $title, array $attributes = []): Photo
    {
        return Photo::create(['title' => ['en' => $title], 'is_published' => true] + $attributes);
    }

    public function test_the_archive_comes_newest_first(): void
    {
        $this->travelTo(now()->subDays(2));
        $this->photo('Oldest');
        $this->travelTo(now()->addDay());
        $this->photo('Middle');
        $this->travelBack();
        $this->photo('Newest');

        $this->getJson('/api/photos')
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Newest', 'Middle', 'Oldest']);
    }

    public function test_a_category_tile_borrows_its_newest_photograph(): void
    {
        $category = Category::create(['type' => Category::TYPE_WORK, 'name' => ['en' => 'Recent'], 'slug' => 'recent', 'position' => 1]);

        $this->travelTo(now()->subDay());
        $this->photo('Older', ['category_id' => $category->id])
            ->addMedia($this->jpeg(1200, 800))->toMediaCollection('image');
        $this->travelBack();
        $newest = $this->photo('Newer', ['category_id' => $category->id]);
        $newest->addMedia($this->jpeg(800, 1200))->toMediaCollection('image');

        $this->assertSame($newest->fresh()->imageUrls()['preview'], $category->tileImageUrls()['preview'] ?? null);
    }
}
