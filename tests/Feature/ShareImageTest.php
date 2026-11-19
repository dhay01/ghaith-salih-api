<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Link previews (WhatsApp, Telegram, iMessage) read one image per page. The
 * originals run to several megabytes and WhatsApp drops images that large, while
 * the WebP sizes are not read by every app. The `share` size is a small JPG made
 * for that one job.
 */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
    }

    /** A real file on disk: a faked upload's temp file is cleaned up before the media is added. */
    protected function jpegOnDisk(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'share').'.jpg';
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_a_cover_gets_a_small_jpg_for_link_previews(): void
    {
        $post = Post::create(['title' => ['en' => 'Share check'], 'is_published' => true]);
        $media = $post->addMedia($this->jpegOnDisk(3000, 2000))->toMediaCollection('image');

        $this->assertTrue($media->fresh()->hasGeneratedConversion('share'), 'no share conversion was made');

        $bytes = Storage::disk($media->conversions_disk ?: $media->disk)->get($media->getPathRelativeToRoot('share'));
        [$width, $height, $type] = getimagesizefromstring($bytes);

        $this->assertSame(IMAGETYPE_JPEG, $type, 'the share image is not a JPG');
        $this->assertSame([1200, 800], [$width, $height], 'the share image was not scaled to fit 1200px');
    }

    public function test_the_api_advertises_the_share_image(): void
    {
        $post = Post::create(['title' => ['en' => 'Api share'], 'is_published' => true, 'published_on' => now()]);
        $post->addMedia($this->jpegOnDisk(1600, 900))->toMediaCollection('image');

        $this->getJson("/api/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.images.share', fn ($url) => is_string($url) && str_ends_with($url, '.jpg'));
    }
}
