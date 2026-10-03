<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A category's tile shows its newest photograph, so uploading work is all it
 * takes to keep the home page and the work page current. A cover uploaded for
 * the category only stands in while it has no photographs.
 */
class CategoryTileImageTest extends TestCase
{
    use RefreshDatabase;

    protected function jpeg(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tile').'.jpg';
        $image = imagecreatetruecolor(400, 300);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    protected function category(string $slug = 'gigapixel'): Category
    {
        return Category::create([
            'type' => Category::TYPE_WORK,
            'slug' => $slug,
            'name' => ['en' => 'Gigapixel'],
            'position' => 1,
        ]);
    }

    protected function photoIn(Category $category, bool $published = true, int $position = 0): Photo
    {
        $photo = Photo::create([
            'category_id' => $category->id,
            'slug' => 'shot-'.$position.'-'.$category->slug,
            'title' => ['en' => 'Shot'],
            'ratio' => '3/2',
            'is_published' => $published,
            'position' => $position,
        ]);

        $photo->addMedia($this->jpeg())->toMediaCollection('image');

        return $photo->fresh();
    }

    public function test_a_category_without_a_cover_borrows_one_of_its_photographs(): void
    {
        $category = $this->category();
        $photo = $this->photoIn($category);

        $urls = $category->fresh()->tileImageUrls();

        $this->assertNotNull($urls, 'a category holding photographs should not render empty');
        $this->assertSame($photo->imageUrls()['preview'], $urls['preview']);
    }

    public function test_its_newest_photograph_wins_over_a_cover_set_in_the_dashboard(): void
    {
        $category = $this->category();
        $this->travelTo(now()->subDay());
        $this->photoIn($category, position: 1);
        $this->travelBack();
        $newest = $this->photoIn($category, position: 2);
        $category->addMedia($this->jpeg())->toMediaCollection('image');

        $this->assertSame(
            $newest->imageUrls()['preview'],
            $category->fresh()->tileImageUrls()['preview'],
            'the tile should follow the latest upload, not a cover picked by hand',
        );
    }

    public function test_a_cover_set_in_the_dashboard_stands_in_until_there_are_photographs(): void
    {
        $category = $this->category();
        $category->addMedia($this->jpeg())->toMediaCollection('image');
        $category = $category->fresh();

        $this->assertSame($category->imageUrls()['preview'], $category->tileImageUrls()['preview']);
    }

    public function test_an_unpublished_photograph_is_not_borrowed(): void
    {
        $category = $this->category();
        $this->photoIn($category, published: false);

        $this->assertNull(
            $category->fresh()->tileImageUrls(),
            'a tile must not surface a photograph that was deliberately unpublished',
        );
    }

    public function test_an_empty_category_still_reports_nothing(): void
    {
        $this->assertNull($this->category()->tileImageUrls());
    }

    public function test_the_api_serves_the_borrowed_image(): void
    {
        $category = $this->category();
        $photo = $this->photoIn($category);

        $this->getJson('/api/categories?type=work')
            ->assertSuccessful()
            ->assertJsonPath('data.0.images.preview', $photo->imageUrls()['preview']);
    }
}
