<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Slices a photo's original into a Deep Zoom tile pyramid.
 *
 * This shells out to the `vips` CLI rather than using a PHP image library: vips
 * streams the image in strips, so a multi-gigapixel original tiles in a few
 * hundred megabytes of memory instead of the many gigabytes a full decode needs.
 */
class GenerateDeepZoomTiles implements ShouldQueue
{
    use Queueable;

    /** Tiling is expensive; a genuine failure should surface, not be retried blindly. */
    public int $tries = 1;

    public function __construct(public Photo $photo) {}

    public function timeout(): int
    {
        return (int) config('gigapixel.timeout');
    }

    public function handle(): void
    {
        $photo = $this->photo->fresh();

        if (! $photo) {
            return;
        }

        $media = $photo->getFirstMedia('image');

        if (! $media) {
            $photo->markTilingFailed('No image has been uploaded for this photo.');

            return;
        }

        $source = $media->getPath();

        if (! is_file($source)) {
            $photo->markTilingFailed('The uploaded file could not be found on disk.');

            return;
        }

        $photo->forceFill([
            'dzi_status' => Photo::TILING_PROCESSING,
            'dzi_progress' => 0,
            'dzi_error' => null,
        ])->save();

        $disk = Storage::disk(config('gigapixel.disk'));
        $relativeBase = trim((string) config('gigapixel.directory'), '/').'/'.$photo->slug;

        $disk->makeDirectory(dirname($relativeBase));
        $this->removeExistingTiles($disk, $relativeBase);

        // vips appends ".dzi" and "_files/" itself, so it is handed a base path
        // with no extension.
        $absoluteBase = $disk->path($relativeBase);

        try {
            // Web-sized versions first: they are what the gallery grid and
            // lightbox need, and they are quick. Tiling can take minutes.
            if (Photo::isOversizedUpload($media)) {
                $this->generateDerivatives($source, $absoluteBase, $photo);
            }

            if ($photo->is_zoomable) {
                $this->runVips($source, $absoluteBase, $photo);
            }
        } catch (ProcessTimedOutException) {
            $photo->markTilingFailed('Tiling exceeded the '.config('gigapixel.timeout').'s limit.');

            return;
        } catch (Throwable $e) {
            Log::error('Deep zoom tiling failed', ['photo' => $photo->slug, 'error' => $e->getMessage()]);
            $photo->markTilingFailed($e->getMessage());

            return;
        }

        if ($photo->is_zoomable && ! $disk->exists($relativeBase.'.dzi')) {
            $photo->markTilingFailed('vips finished but produced no .dzi descriptor.');

            return;
        }

        $photo->forceFill([
            'dzi_path' => $photo->is_zoomable ? $relativeBase.'.dzi' : null,
            'dzi_meta' => $photo->is_zoomable
                ? $this->readDziMeta($disk->path($relativeBase.'.dzi'))
                : null,
            'dzi_status' => Photo::TILING_READY,
            'dzi_media_id' => $media->getKey(),
            'dzi_error' => null,
            'dzi_progress' => 100,
            'dzi_generated_at' => now(),
        ])->save();
    }

    /**
     * Web-sized versions of an original too large for GD. `vips thumbnail` reads
     * only the resolution it needs, so this stays cheap even on a huge file.
     */
    protected function generateDerivatives(string $source, string $absoluteBase, Photo $photo): void
    {
        $derivatives = (array) config('gigapixel.derivatives');
        $done = 0;

        foreach ($derivatives as $name => $longestEdge) {
            $process = new Process([
                (string) config('gigapixel.binary'),
                'thumbnail',
                $source,
                $absoluteBase.'-'.$name.'.webp',
                (string) $longestEdge,
                '--size', 'down',
            ]);

            $process->setTimeout((float) config('gigapixel.timeout'));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'Could not build the '.$name.' version: '.
                    (trim($process->getErrorOutput()) ?: 'vips exited with '.$process->getExitCode()),
                );
            }

            // Nudges the bar so it is not pinned at zero for the whole of this
            // phase, which on a large file is most of the run. Tiling's own
            // percentages start at 5 and take over from here.
            $photo->newQuery()
                ->whereKey($photo->getKey())
                ->update(['dzi_progress' => ++$done]);
        }
    }

    /**
     * Reads the geometry vips wrote into the .dzi so the API can describe the tile
     * source without the browser fetching this file.
     *
     * @return array<string, mixed>|null
     */
    protected function readDziMeta(string $dziPath): ?array
    {
        if (! is_file($dziPath)) {
            return null;
        }

        $xml = @simplexml_load_file($dziPath);

        if (! $xml) {
            return null;
        }

        $size = $xml->Size ?? null;

        if (! $size) {
            return null;
        }

        return [
            'width' => (int) $size['Width'],
            'height' => (int) $size['Height'],
            'tile_size' => (int) $xml['TileSize'],
            'overlap' => (int) $xml['Overlap'],
            'format' => (string) $xml['Format'],
        ];
    }

    protected function runVips(string $source, string $absoluteBase, Photo $photo): void
    {
        $process = new Process([
            (string) config('gigapixel.binary'),
            'dzsave',
            $source,
            $absoluteBase,
            '--suffix', '.jpg[Q='.config('gigapixel.quality').']',
            '--tile-size', (string) config('gigapixel.tile_size'),
            // Makes vips emit "NN% complete" as it works.
            '--vips-progress',
        ]);

        $process->setTimeout((float) config('gigapixel.timeout'));

        $lastWritten = 0;

        $process->run(function (string $type, string $buffer) use ($photo, &$lastWritten): void {
            if (! preg_match_all('/(\d+)% complete/', $buffer, $matches)) {
                return;
            }

            $percent = (int) end($matches[1]);

            // vips reports every single percent; writing each one would be a
            // hundred queries per photo for no visible benefit.
            if ($percent < $lastWritten + 5 && $percent < 100) {
                return;
            }

            $lastWritten = $percent;

            // Quietly: this is a progress ping, not a change worth waking the
            // observer that queues tiling.
            $photo->newQuery()
                ->whereKey($photo->getKey())
                ->update(['dzi_progress' => min(100, $percent)]);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                trim($process->getErrorOutput()) ?: 'vips exited with code '.$process->getExitCode(),
            );
        }
    }

    /** Re-tiling must not leave orphaned tiles behind from a previous upload. */
    protected function removeExistingTiles(Filesystem $disk, string $relativeBase): void
    {
        $disk->delete($relativeBase.'.dzi');
        $disk->deleteDirectory($relativeBase.'_files');

        foreach (array_keys((array) config('gigapixel.derivatives')) as $name) {
            $disk->delete($relativeBase.'-'.$name.'.webp');
        }
    }

    public function failed(Throwable $e): void
    {
        $this->photo->fresh()?->markTilingFailed($e->getMessage());
    }
}
