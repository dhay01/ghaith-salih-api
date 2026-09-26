<?php

namespace Tests\Feature;

use App\Jobs\GenerateDeepZoomTiles;
use App\Models\Photo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * A pyramid whose descriptor uploads but whose tiles do not is worse than one
 * that fails outright: the viewer loads the .dzi, believes the image exists and
 * then 404s on every tile it asks for. This happened in production on a
 * 41066x10120 panorama — the .dzi and 7 of 17 levels arrived, the other 10 did
 * not, and the job reported success. So the sync now proves what it uploaded.
 */
class TileSyncVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** A scratch tree shaped like the one vips writes: a descriptor plus levels. */
    protected function scratchTree(): string
    {
        $dir = storage_path('framework/testing/scratch-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir.'/panorama_files/0');
        File::ensureDirectoryExists($dir.'/panorama_files/1');

        File::put($dir.'/panorama.dzi', '<Image TileSize="512" Overlap="1" Format="jpg" />');
        File::put($dir.'/panorama-thumb.webp', 'thumb-bytes');
        File::put($dir.'/panorama_files/0/0_0.jpg', 'level-0');
        File::put($dir.'/panorama_files/1/0_0.jpg', 'level-1-a');
        File::put($dir.'/panorama_files/1/1_0.jpg', 'level-1-b');

        return $dir;
    }

    protected function job(): GenerateDeepZoomTiles
    {
        return new GenerateDeepZoomTiles(Photo::create([
            'slug' => 'panorama',
            'title' => ['en' => 'Panorama'],
            'ratio' => '4/1',
            'is_zoomable' => true,
        ]));
    }

    public function test_every_file_in_the_tree_is_uploaded_under_its_relative_path(): void
    {
        Storage::fake('tiles');
        $disk = Storage::disk('tiles');
        $scratch = $this->scratchTree();

        $synced = $this->job()->syncLocalTree($scratch, $disk, 'tiles');

        foreach ([
            'tiles/panorama.dzi',
            'tiles/panorama-thumb.webp',
            'tiles/panorama_files/0/0_0.jpg',
            'tiles/panorama_files/1/0_0.jpg',
            'tiles/panorama_files/1/1_0.jpg',
        ] as $key) {
            $this->assertTrue($disk->exists($key), $key.' should have been uploaded.');
            $this->assertArrayHasKey($key, $synced, $key.' should be reported as synced.');
        }

        $this->assertSame('level-1-b', $disk->get('tiles/panorama_files/1/1_0.jpg'));

        File::deleteDirectory($scratch);
    }

    public function test_a_tile_that_never_arrives_fails_the_job_instead_of_publishing_a_broken_pyramid(): void
    {
        // A disk that accepts every write and keeps nothing — which is what a
        // sync cut off part way looks like from the job's point of view.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturnTrue();
        $disk->shouldReceive('files')->andReturn([]);

        $scratch = $this->scratchTree();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not reach storage');

        try {
            $this->job()->syncLocalTree($scratch, $disk, 'tiles');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    public function test_a_single_dropped_tile_is_re_sent_rather_than_failing_the_whole_run(): void
    {
        Storage::fake('tiles');
        $real = Storage::disk('tiles');
        $scratch = $this->scratchTree();
        $dropped = 'tiles/panorama_files/1/1_0.jpg';
        $attempts = 0;

        // The sync only ever asks a disk to put files and list directories, so
        // those are the only two worth standing in for. This one swallows the
        // first write of one tile, the way a transient 500 would.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')
            ->andReturnUsing(function ($path, $contents, $options = []) use ($real, $dropped, &$attempts) {
                if ($path === $dropped && ++$attempts === 1) {
                    return true;
                }

                return $real->put($path, $contents, $options);
            });
        $disk->shouldReceive('files')->andReturnUsing(fn ($directory) => $real->files($directory));

        $synced = $this->job()->syncLocalTree($scratch, $disk, 'tiles');

        $this->assertArrayHasKey($dropped, $synced);
        $this->assertTrue($real->exists($dropped), 'The retry pass should have re-sent the dropped tile.');
        $this->assertSame('level-1-b', $real->get($dropped));

        File::deleteDirectory($scratch);
    }

    public function test_progress_is_reported_as_files_land(): void
    {
        Storage::fake('tiles');
        $scratch = $this->scratchTree();
        $seen = [];

        $this->job()->syncLocalTree(
            $scratch,
            Storage::disk('tiles'),
            'tiles',
            [],
            function (int $done, int $total) use (&$seen) {
                $seen[] = [$done, $total];
            },
        );

        // Five files, reported on the last one: the callback is throttled so a
        // few thousand tiles do not become a few thousand database writes.
        $this->assertSame([[5, 5]], $seen);

        File::deleteDirectory($scratch);
    }
}
