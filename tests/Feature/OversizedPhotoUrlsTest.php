<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OversizedPhotoUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_photos_do_not_advertise_the_original_as_a_web_size(): void
    {
        $photo = $this->oversizedPhoto();
        $original = $photo->getFirstMedia('image')->getFullUrl();
        $urls = $photo->imageUrls();

        $this->assertNull($urls['thumb']);
        $this->assertNull($urls['preview']);
        $this->assertNull($urls['full']);
        $this->assertSame($original, $urls['original']);

        $this->getJson('/api/photos')
            ->assertOk()
            ->assertJsonPath('data.0.images.thumb', null)
            ->assertJsonPath('data.0.images.original', $original);
    }

    public function test_vips_derivatives_are_used_once_they_exist_even_if_tiling_is_still_running(): void
    {
        $photo = $this->oversizedPhoto();
        $photo->forceFill(['dzi_status' => Photo::TILING_PROCESSING])->save();

        $disk = Storage::disk(config('gigapixel.disk'));
        $base = $photo->derivativeBase();

        foreach (array_keys(config('gigapixel.derivatives')) as $name) {
            $disk->put($base.'-'.$name.'.webp', 'webp');
        }

        $urls = $photo->fresh()->imageUrls();

        $this->assertSame($disk->url($base.'-thumb.webp'), $urls['thumb']);
        $this->assertSame($disk->url($base.'-preview.webp'), $urls['preview']);
        $this->assertSame($disk->url($base.'-full.webp'), $urls['full']);
        $this->assertNotSame($urls['thumb'], $urls['original']);
    }

    public function test_the_photo_list_does_not_embed_the_original_in_an_img_tag(): void
    {
        $photo = $this->oversizedPhoto();
        $original = $photo->getFirstMedia('image')->getUrl();

        $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<img\b[^>]*\bsrc="[^"]*'.preg_quote($original, '/').'[^"]*"/i',
            $html,
        );
    }

    protected function oversizedPhoto(): Photo
    {
        $photo = Photo::create([
            'slug' => 'panorama-check',
            'title' => ['en' => 'Panorama check'],
            'ratio' => '16/5',
            'is_zoomable' => true,
            'is_published' => true,
        ]);

        $photo->addMedia($this->jpeg())->preservingOriginal()->toMediaCollection('image');

        $media = $photo->fresh()->getFirstMedia('image');
        $media->size = (int) config('gigapixel.large_file_bytes') + 1;
        $media->save();

        return $photo->fresh();
    }

    protected function jpeg(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oversize').'.jpg';
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }
}
