<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A category with photographs in it but no cover of its own rendered as an empty
 * labelled tile. That reads as broken rather than as unset, because the
 * photographs are visibly right there in the category.
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

    public function test_its_own_cover_still_wins(): void
    {
        $category = $this->category();
        $this->photoIn($category);
        $category->addMedia($this->jpeg())->toMediaCollection('image');
        $category = $category->fresh();

        $this->assertSame(
            $category->imageUrls()['preview'],
            $category->tileImageUrls()['preview'],
            'choosing a cover must remain a decision the dashboard can make',
        );
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
