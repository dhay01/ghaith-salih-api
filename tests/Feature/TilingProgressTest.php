<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The progress readout has to reset cleanly, or a stale percentage misleads. */
class TilingProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function photo(array $attributes = []): Photo
    {
        return Photo::create(array_merge([
            'slug' => 'progress-check',
            'title' => ['en' => 'Progress check'],
            'ratio' => '3/2',
            'is_zoomable' => true,
            'is_published' => true,
        ], $attributes));
    }

    public function test_a_failure_clears_the_percentage(): void
    {
        $photo = $this->photo();
        $photo->forceFill(['dzi_status' => Photo::TILING_PROCESSING, 'dzi_progress' => 60])->save();

        $photo->markTilingFailed('vips exploded');

        $photo->refresh();

        $this->assertSame(Photo::TILING_FAILED, $photo->dzi_status);
        $this->assertNull($photo->dzi_progress, 'A failed job must not leave a percentage showing.');
        $this->assertSame('vips exploded', $photo->dzi_error);
    }

    public function test_requeuing_resets_the_percentage_and_the_error(): void
    {
        // Without this the queue runs inline and the job finishes before the
        // assertions, so "queued" is never observable.
        Queue::fake();

        $photo = $this->photo();
        $photo->forceFill([
            'dzi_status' => Photo::TILING_FAILED,
            'dzi_progress' => 40,
            'dzi_error' => 'previous failure',
        ])->save();

        // Pretend an image is attached so tiling is considered necessary.
        $photo->addMedia($this->jpegPath())->preservingOriginal()->toMediaCollection('image');
        $photo->refresh();
        $photo->forceFill(['dzi_status' => null, 'dzi_media_id' => null])->save();

        Photo::queueTilingFor($photo->fresh());

        $photo->refresh();

        $this->assertSame(Photo::TILING_QUEUED, $photo->dzi_status);
        $this->assertNull($photo->dzi_progress);
        $this->assertNull($photo->dzi_error);
    }

    public function test_the_photo_list_shows_the_progress_column(): void
    {
        $photo = $this->photo();
        $photo->forceFill(['dzi_status' => Photo::TILING_PROCESSING, 'dzi_progress' => 42])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            ->assertSee('Building 42%', escape: false);
    }

    protected function jpegPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prog').'.jpg';
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }
}
