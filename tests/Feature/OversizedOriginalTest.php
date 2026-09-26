<?php

namespace Tests\Feature;

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A photograph too large for GD gets its web-sized versions from vips, which
 * runs on the queue. Between the upload finishing and that job completing there
 * is nothing small enough to send.
 *
 * The only other file is the original, and for a gigapixel stitch that is several
 * hundred megabytes. Serving it renders the gallery unusable and costs whoever
 * opened it their data — a /work page was measured at 379 MB transferred. The
 * placeholder is the honest answer until the tiles exist.
 */
class OversizedOriginalTest extends TestCase
{
    use RefreshDatabase;

    protected function photoWithLargeOriginal(): Photo
    {
        // The window this guards is between the upload finishing and the tiling
        // job completing, so the job must not be allowed to run: the test suite
        // dispatches synchronously and vips would tile this in milliseconds.
        Queue::fake();

        // Rather than generate a real gigapixel file, lower what counts as large.
        config(['gigapixel.large_file_bytes' => 1]);

        $path = tempnam(sys_get_temp_dir(), 'big').'.jpg';
        $image = imagecreatetruecolor(400, 300);
        imagejpeg($image, $path);
        imagedestroy($image);

        $photo = Photo::create([
            'slug' => 'oversized',
            'title' => ['en' => 'Oversized'],
            'ratio' => '3/2',
            'is_published' => true,
        ]);

        $photo->addMedia($path)->toMediaCollection('image');

        return $photo->fresh();
    }

    public function test_an_untiled_original_is_not_served_to_the_browser(): void
    {
        $photo = $this->photoWithLargeOriginal();
        $media = $photo->getFirstMedia('image');

        $this->assertTrue(
            $photo::isOversizedUpload($media),
            'the threshold override should make this count as a large upload',
        );

        $urls = $photo->imageUrls();

        // The original stays reachable under its own key — something may still
        // want the file deliberately. What must never happen is a size meant for
        // an img tag resolving to it.
        foreach (['thumb', 'preview', 'full'] as $size) {
            $this->assertNull(
                $urls[$size] ?? null,
                "{$size} must be empty until vips has written it, never the original",
            );
        }

        $this->assertNotContains(
            $media->getFullUrl(),
            [$urls['thumb'] ?? null, $urls['preview'] ?? null, $urls['full'] ?? null],
            'the untouched original must never be handed to a browser as a display size',
        );
    }

    public function test_the_api_reports_no_image_rather_than_the_original(): void
    {
        $this->photoWithLargeOriginal();

        $this->getJson('/api/photos')
            ->assertSuccessful()
            ->assertJsonPath('data.0.images.preview', null)
            ->assertJsonPath('data.0.images.thumb', null)
            ->assertJsonPath('data.0.images.full', null);
    }

    public function test_an_ordinary_photograph_is_unaffected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'small').'.jpg';
        $image = imagecreatetruecolor(400, 300);
        imagejpeg($image, $path);
        imagedestroy($image);

        $photo = Photo::create([
            'slug' => 'ordinary',
            'title' => ['en' => 'Ordinary'],
            'ratio' => '3/2',
            'is_published' => true,
        ]);
        $photo->addMedia($path)->toMediaCollection('image');

        $urls = $photo->fresh()->imageUrls();

        $this->assertNotNull($urls, 'a normal upload still has its GD conversions');
        $this->assertArrayHasKey('preview', $urls);
    }
}
