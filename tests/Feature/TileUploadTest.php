<?php

namespace Tests\Feature;

use App\Jobs\GenerateDeepZoomTiles;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_scratch_tree_is_uploaded_under_the_tile_prefix(): void
    {
        Storage::fake('s3');

        $scratch = sys_get_temp_dir().'/vips-scratch-'.bin2hex(random_bytes(4));
        mkdir($scratch.'/photo_files/0', 0777, true);
        file_put_contents($scratch.'/photo.dzi', '<Image TileSize="512"/>');
        file_put_contents($scratch.'/photo_files/0/0_0.jpg', 'tile');
        file_put_contents($scratch.'/photo-thumb.webp', 'webp');

        $photo = Photo::create([
            'slug' => 'upload-check',
            'title' => ['en' => 'Upload check'],
            'ratio' => '3/2',
            'is_published' => true,
        ]);

        (new GenerateDeepZoomTiles($photo))->syncLocalTree($scratch, Storage::disk('s3'), 'tiles');

        Storage::disk('s3')->assertExists('tiles/photo.dzi');
        Storage::disk('s3')->assertExists('tiles/photo_files/0/0_0.jpg');
        Storage::disk('s3')->assertExists('tiles/photo-thumb.webp');

        array_map('unlink', glob($scratch.'/photo_files/0/*') ?: []);
        @rmdir($scratch.'/photo_files/0');
        @rmdir($scratch.'/photo_files');
        @unlink($scratch.'/photo.dzi');
        @unlink($scratch.'/photo-thumb.webp');
        @rmdir($scratch);
    }
}
