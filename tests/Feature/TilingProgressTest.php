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

    public function test_the_photo_list_says_which_phase_the_job_is_in(): void
    {
        $photo = $this->photo();
        $photo->forceFill([
            'dzi_status' => Photo::TILING_PROCESSING,
            'dzi_progress' => 42,
            'dzi_stage' => 'Uploading tiles · 900 of 2,194',
        ])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            // The percentage alone was the whole problem: it reached 100 when
            // slicing ended and sat there for the minutes the upload took.
            ->assertSee('Uploading tiles · 900 of 2,194', escape: false)
            ->assertSee('42%', escape: false);
    }

    public function test_the_photo_list_still_reads_sensibly_with_no_phase_recorded(): void
    {
        $photo = $this->photo();
        $photo->forceFill(['dzi_status' => Photo::TILING_PROCESSING, 'dzi_progress' => 5])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            ->assertSee('Working', escape: false);
    }

    public function test_a_failure_reason_is_readable_without_hovering(): void
    {
        $photo = $this->photo();
        $photo->markTilingFailed('412 of 2,194 files did not reach storage under tiles.');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            ->assertSee('412 of 2,194 files did not reach storage under tiles.', escape: false);
    }

    public function test_the_edit_page_shows_the_current_phase(): void
    {
        $photo = $this->photo();
        $photo->addMedia($this->jpegPath())->preservingOriginal()->toMediaCollection('image');
        $photo->forceFill([
            'dzi_status' => Photo::TILING_PROCESSING,
            'dzi_progress' => 80,
            'dzi_stage' => 'Slicing tiles · 61%',
        ])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos/'.$photo->getRouteKey().'/edit')
            ->assertSuccessful()
            ->assertSee('Slicing tiles · 61%', escape: false)
            // Answers the other half of the question: whether the tab has to stay open.
            ->assertSee('you can leave this page', escape: false);
    }

    public function test_the_queue_wait_is_labelled_rather_than_left_blank(): void
    {
        Queue::fake();

        $photo = $this->photo();
        $photo->addMedia($this->jpegPath())->preservingOriginal()->toMediaCollection('image');
        $photo->refresh();
        $photo->forceFill(['dzi_status' => null, 'dzi_media_id' => null])->save();

        Photo::queueTilingFor($photo->fresh());

        $this->assertSame('Waiting for the queue worker', $photo->fresh()->dzi_stage);
    }

    public function test_the_progress_styles_are_sent_once_per_page_not_once_per_row(): void
    {
        foreach (range(1, 4) as $i) {
            $this->photo(['slug' => 'styled-'.$i, 'title' => ['en' => 'Styled '.$i]])
                ->forceFill([
                    'dzi_status' => Photo::TILING_PROCESSING,
                    'dzi_progress' => 50,
                    'dzi_stage' => 'Slicing tiles · 50%',
                ])->saveQuietly();
        }

        $html = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/photos')
            ->assertSuccessful()
            ->getContent();

        $this->assertSame(4, substr_count($html, 'class="tiling '), 'Every row should render the column.');

        // Counted on a string that appears once in the stylesheet, not on a
        // selector: several rules share the `.tiling__track` substring.
        $this->assertSame(
            1,
            substr_count($html, '@keyframes tiling-pulse'),
            'The stylesheet belongs in the head once, not once per row.',
        );
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
