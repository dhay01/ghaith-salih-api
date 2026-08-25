<?php

namespace Tests\Feature;

use App\Jobs\GenerateDeepZoomTiles;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A gigapixel original cannot arrive in one POST, so the browser slices it. These
 * tests pin the reassembly, since a silent mistake there would corrupt an image
 * rather than fail loudly.
 */
class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Chunks and assembled files are real writes; keep them out of the
        // project's storage directory and isolated between tests.
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * A real JPEG, because the media collection checks the assembled file's mime
     * type — random bytes would be rejected before the reassembly is even tested.
     */
    protected function jpegBytes(int $side = 700): string
    {
        $image = imagecreatetruecolor($side, $side);

        // Noise, so the file does not compress down to a single chunk.
        for ($x = 0; $x < $side; $x += 2) {
            for ($y = 0; $y < $side; $y += 2) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }

        ob_start();
        imagejpeg($image, null, 92);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function photo(bool $zoomable = true): Photo
    {
        return Photo::create([
            'slug' => 'test-pano',
            'title' => ['en' => 'Test Panorama'],
            'ratio' => '3/2',
            'is_zoomable' => $zoomable,
            'is_published' => true,
        ]);
    }

    /** Sends $body in pieces and returns the finish response. */
    protected function upload(User $user, Photo $photo, string $body, int $chunkSize, string $name = 'pano.jpg')
    {
        $uploadId = (string) Str::uuid();
        $pieces = str_split($body, $chunkSize);

        foreach ($pieces as $index => $piece) {
            $this->actingAs($user)
                ->post('/admin/large-upload/chunk', [
                    'upload_id' => $uploadId,
                    'index' => $index,
                    'chunk' => UploadedFile::fake()->createWithContent("part-{$index}", $piece),
                ])
                ->assertSuccessful();
        }

        return $this->actingAs($user)->postJson('/admin/large-upload/finish', [
            'upload_id' => $uploadId,
            'photo' => $photo->getKey(),
            'filename' => $name,
            'chunks' => count($pieces),
            'size' => strlen($body),
        ]);
    }

    public function test_a_file_sent_in_pieces_is_reassembled_byte_for_byte(): void
    {
        Queue::fake();
        $photo = $this->photo();

        $body = $this->jpegBytes();

        // A chunk size that does not divide the file evenly, so the last piece is short.
        $this->assertGreaterThan(8_192 * 3, strlen($body), 'test image should span several chunks');

        $this->upload($this->admin(), $photo, $body, 8_192)->assertSuccessful();

        $media = $photo->fresh()->getFirstMedia('image');

        $this->assertNotNull($media);
        $this->assertSame(strlen($body), $media->size);
        $this->assertSame(hash('sha256', $body), hash_file('sha256', $media->getPath()));
    }

    public function test_reassembly_queues_deep_zoom_tiling(): void
    {
        Queue::fake();
        $photo = $this->photo(zoomable: true);

        $this->upload($this->admin(), $photo, $this->jpegBytes(400), 8_192)->assertSuccessful();

        Queue::assertPushed(GenerateDeepZoomTiles::class);
    }

    public function test_a_missing_piece_is_refused_rather_than_saved_corrupt(): void
    {
        Queue::fake();
        $photo = $this->photo();
        $user = $this->admin();
        $uploadId = (string) Str::uuid();

        // Two pieces sent, three claimed.
        foreach ([0, 1] as $index) {
            $this->actingAs($user)->post('/admin/large-upload/chunk', [
                'upload_id' => $uploadId,
                'index' => $index,
                'chunk' => UploadedFile::fake()->createWithContent("part-{$index}", 'abcd'),
            ])->assertSuccessful();
        }

        $this->actingAs($user)->postJson('/admin/large-upload/finish', [
            'upload_id' => $uploadId,
            'photo' => $photo->getKey(),
            'filename' => 'pano.jpg',
            'chunks' => 3,
            'size' => 12,
        ])->assertStatus(422);

        $this->assertNull($photo->fresh()->getFirstMedia('image'));
    }

    public function test_a_size_mismatch_is_refused(): void
    {
        Queue::fake();
        $photo = $this->photo();

        // Claim a size that does not match what was actually sent.
        $uploadId = (string) Str::uuid();
        $user = $this->admin();

        $this->actingAs($user)->post('/admin/large-upload/chunk', [
            'upload_id' => $uploadId,
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part-0', 'abcd'),
        ])->assertSuccessful();

        $this->actingAs($user)->postJson('/admin/large-upload/finish', [
            'upload_id' => $uploadId,
            'photo' => $photo->getKey(),
            'filename' => 'pano.jpg',
            'chunks' => 1,
            'size' => 999,
        ])->assertStatus(422);
    }

    public function test_a_non_admin_cannot_upload(): void
    {
        $photo = $this->photo();
        $user = User::factory()->create();   // no is_admin

        $this->actingAs($user)->post('/admin/large-upload/chunk', [
            'upload_id' => (string) Str::uuid(),
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part-0', 'abcd'),
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->post('/admin/large-upload/chunk', [
            'upload_id' => (string) Str::uuid(),
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('part-0', 'abcd'),
        ])->assertRedirect('/admin/login');
    }

    public function test_the_uploader_renders_on_the_photo_edit_page(): void
    {
        $photo = $this->photo();

        $response = $this->actingAs($this->admin())
            ->get('/admin/photos/'.$photo->getRouteKey().'/edit');

        $response->assertSuccessful();

        // escape: false throughout — the URLs are embedded via @js(), which
        // backslash-escapes their slashes.
        $response->assertSee('Large original', escape: false);
        $response->assertSee('largeFileUpload', escape: false);
        $response->assertSee('large-upload', escape: false);
    }

    public function test_a_malformed_upload_id_is_rejected(): void
    {
        // Not postJson: the payload carries a file, which cannot be JSON encoded.
        $this->actingAs($this->admin())
            ->post('/admin/large-upload/chunk', [
                'upload_id' => str_repeat('../', 12),   // 36 chars, passes the length rule
                'index' => 0,
                'chunk' => UploadedFile::fake()->createWithContent('part-0', 'abcd'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles('chunked-uploads'));
    }
}
