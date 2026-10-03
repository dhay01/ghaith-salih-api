<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every photograph used to be laid out at the ratio typed into the dashboard,
 * which nobody changed from its 3/2 default — so portraits and panoramas were
 * cropped to landscape. Shapes are now measured from the image itself.
 */
class PhotoShapeTest extends TestCase
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

    public function test_an_upload_records_the_shape_of_the_image(): void
    {
        $photo = $this->photo('Portrait');
        $photo->addMedia($this->jpeg(800, 1200))->toMediaCollection('image');

        $this->assertSame('2/3', $photo->fresh()->imageRatio());

        $this->getJson('/api/photos')->assertOk()->assertJsonPath('data.0.ratio', '2/3');
    }

    public function test_a_photo_with_no_image_keeps_its_typed_ratio(): void
    {
        $this->photo('Waiting for a file', ['ratio' => '16/9']);

        $this->getJson('/api/photos')->assertOk()->assertJsonPath('data.0.ratio', '16/9');
    }

    public function test_a_large_upload_is_measured_from_its_vips_thumbnail(): void
    {
        config(['gigapixel.large_file_bytes' => 1]);

        $photo = $this->photo('Panorama');
        $media = $photo->addMedia($this->jpeg(400, 100))->toMediaCollection('image');

        $thumb = imagecreatetruecolor(600, 150);
        ob_start();
        imagewebp($thumb);
        Storage::disk(config('gigapixel.disk'))->put($photo->derivativeBase().'-thumb.webp', ob_get_clean());

        $photo->rememberShapeOf($media);

        $this->assertSame('4/1', $photo->fresh()->imageRatio());
    }

    public function test_the_backfill_measures_images_uploaded_before_shapes_were_recorded(): void
    {
        $photo = $this->photo('Uploaded long ago');
        $media = $photo->addMedia($this->jpeg(900, 900))->toMediaCollection('image');
        $media->forgetCustomProperty('ratio')->saveQuietly();
        $this->assertNull($photo->fresh()->imageRatio());

        $this->artisan('media:remember-shapes')->assertSuccessful();

        $this->assertSame('1/1', $photo->fresh()->imageRatio());
    }

    public function test_a_category_tile_carries_the_shape_of_the_image_it_shows(): void
    {
        $borrowing = Category::create(['type' => Category::TYPE_WORK, 'name' => ['en' => 'Borrowing'], 'slug' => 'borrowing', 'position' => 1]);
        $this->photo('Tall', ['category_id' => $borrowing->id])
            ->addMedia($this->jpeg(800, 1200))->toMediaCollection('image');

        $own = Category::create(['type' => Category::TYPE_WORK, 'name' => ['en' => 'Own'], 'slug' => 'own', 'position' => 2]);
        $own->addMedia($this->jpeg(1200, 800))->toMediaCollection('image');

        $tiles = collect($this->getJson('/api/categories')->assertOk()->json('data'))->keyBy('slug');

        $this->assertSame('2/3', $tiles['borrowing']['ratio']);
        $this->assertSame('3/2', $tiles['own']['ratio']);
    }
}
