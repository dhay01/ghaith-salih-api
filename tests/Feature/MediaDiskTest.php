<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An upload that lands on the `local` disk is written to storage/app/private,
 * which the web server does not serve: the image 403s and the gallery renders an
 * empty tile with no error anywhere. This happened once in development, so the
 * disk is now asserted rather than assumed.
 */
class MediaDiskTest extends TestCase
{
    use RefreshDatabase;

    /** A real file on disk: the faked upload's temp file is cleaned up too early. */
    protected function jpeg(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mediadisk').'.jpg';
        $image = imagecreatetruecolor(400, 300);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_photo_uploads_land_on_a_publicly_served_disk(): void
    {
        $photo = Photo::create([
            'slug' => 'disk-check',
            'title' => ['en' => 'Disk check'],
            'ratio' => '3/2',
            'is_published' => true,
        ]);

        $photo->addMedia($this->jpeg())
            ->preservingOriginal()
            ->toMediaCollection('image');

        $media = $photo->fresh()->getFirstMedia('image');

        $this->assertSame('public', $media->disk);
        $this->assertStringContainsString('/storage/', $media->getFullUrl());
    }

    public function test_post_covers_land_on_the_same_disk(): void
    {
        $post = Post::create([
            'slug' => 'disk-check-post',
            'title' => ['en' => 'Disk check post'],
            'is_published' => true,
        ]);

        $post->addMedia($this->jpeg())
            ->preservingOriginal()
            ->toMediaCollection('image');

        $this->assertSame('public', $post->fresh()->getFirstMedia('image')->disk);
    }

    public function test_the_configured_media_disk_is_publicly_served(): void
    {
        $disk = config('media-library.disk_name');

        $this->assertSame('public', $disk, 'Uploads must not go to a private disk.');
        $this->assertNotSame(
            storage_path('app/private'),
            config("filesystems.disks.{$disk}.root"),
            'The media disk points at the private storage directory.',
        );
    }
}
