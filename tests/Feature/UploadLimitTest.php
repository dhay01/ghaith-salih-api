<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Tests\TestCase;

/**
 * Every image field in the dashboard tells the editor how big a file it takes
 * (gigapixel.large_file_bytes). Livewire enforces its own, separate ceiling on the
 * upload itself — 12 MB by default — so a 15 MB photo passed the field's check,
 * was refused by the server, and the post saved without its cover.
 */
class UploadLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tmp-for-tests');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function upload(int $kilobytes)
    {
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));

        return $this->post(
            $url,
            ['files' => [UploadedFile::fake()->create('photo.jpg', $kilobytes, 'image/jpeg')]],
            ['Accept' => 'application/json'],
        );
    }

    public function test_the_server_ceiling_is_the_one_the_fields_advertise(): void
    {
        $advertised = (int) (config('gigapixel.large_file_bytes') / 1024);

        $this->assertContains("max:{$advertised}", FileUploadConfiguration::rules());
    }

    public function test_a_photo_between_twelve_megabytes_and_the_advertised_limit_uploads(): void
    {
        $this->upload(20 * 1024)->assertOk();
    }

    public function test_a_file_over_the_advertised_limit_is_refused(): void
    {
        $over = (int) (config('gigapixel.large_file_bytes') / 1024) + 1024;

        $this->upload($over)->assertSessionHasErrors();
    }
}
