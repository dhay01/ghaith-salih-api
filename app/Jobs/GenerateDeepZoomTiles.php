<?php

namespace App\Jobs;

use App\Models\Photo;
use Aws\CommandPool;
use Closure;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

    /**
     * The slice of the overall bar each phase owns.
     *
     * vips reports a percentage for its own slicing and nothing else, so a bar
     * built on that alone reaches 100% and then sits there for the minutes the
     * upload takes — which reads as a hung job. These weights are rough wall
     * clock shares on a large panorama, so the bar keeps moving throughout.
     */
    private const STAGES = [
        'fetching' => [0, 5],
        'derivatives' => [5, 15],
        'slicing' => [15, 70],
        'uploading' => [70, 100],
    ];

    /** Parallel tile uploads. R2 is happy well past this; the queue worker's memory is the limit. */
    private const UPLOAD_CONCURRENCY = 16;

    /** Tiling is expensive; a genuine failure should surface, not be retried blindly. */
    public int $tries = 1;

    public function __construct(public Photo $photo) {}

    public function timeout(): int
    {
        return (int) config('gigapixel.timeout');
    }

    /**
     * Records what the job is doing right now, and how far along it is overall.
     *
     * @param  float|null  $fraction  progress within this phase, 0.0 to 1.0
     */
    protected function report(string $stage, string $label, ?float $fraction = null): void
    {
        [$from, $to] = self::STAGES[$stage];

        // Quietly, by primary key: a progress ping is not a change worth waking
        // the observer that queues tiling, and the model in hand is stale.
        $this->photo->newQuery()
            ->whereKey($this->photo->getKey())
            ->update([
                'dzi_stage' => $label,
                'dzi_progress' => (int) round($from + ($to - $from) * max(0.0, min(1.0, $fraction ?? 0.0))),
            ]);
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

        // Before the download, not after: streaming a multi-hundred-megabyte
        // original off object storage is minutes of work on its own, and left
        // until later the admin shows "Waiting" for all of it.
        $photo->forceFill([
            'dzi_status' => Photo::TILING_PROCESSING,
            'dzi_progress' => 0,
            'dzi_stage' => 'Fetching the original',
            'dzi_error' => null,
        ])->save();

        try {
            [$source, $deleteSource] = $this->materializeSource($media);
        } catch (Throwable $e) {
            $photo->markTilingFailed($e->getMessage());

            return;
        }

        $disk = Storage::disk(config('gigapixel.disk'));
        $relativeBase = trim((string) config('gigapixel.directory'), '/').'/'.$photo->slug;

        $this->removeExistingTiles($disk, $relativeBase);

        // vips appends ".dzi" and "_files/" itself, so it is handed a base path
        // with no extension. Object storage has no local path: write a scratch
        // tree, then upload.
        [$absoluteBase, $scratch] = $this->vipsOutputBase($disk, $relativeBase);
        $dziMeta = null;

        // Derivatives go up before tiling starts, so they are not re-sent after.
        $synced = [];

        try {
            // Web-sized versions first: they are what the gallery grid and
            // lightbox need, and they are quick. Tiling can take minutes.
            if (Photo::isOversizedUpload($media)) {
                $this->generateDerivatives($source, $absoluteBase);

                // Web-sized files first, so the admin and gallery can preview
                // while dzsave is still chewing on the original.
                if ($scratch) {
                    $this->report('derivatives', 'Uploading web-sized versions', 0.8);
                    $synced = $this->syncLocalTree($scratch, $disk, dirname($relativeBase));
                }
            }

            if ($photo->is_zoomable) {
                $this->runVips($source, $absoluteBase);
            }

            $dziMeta = $photo->is_zoomable
                ? $this->readDziMeta($absoluteBase.'.dzi')
                : null;

            if ($scratch) {
                $this->syncLocalTree(
                    $scratch,
                    $disk,
                    dirname($relativeBase),
                    $synced,
                    fn (int $done, int $total) => $this->report(
                        'uploading',
                        'Uploading tiles · '.number_format($done).' of '.number_format($total),
                        $total > 0 ? $done / $total : null,
                    ),
                );
            }
        } catch (ProcessTimedOutException) {
            $photo->markTilingFailed('Tiling exceeded the '.config('gigapixel.timeout').'s limit.');

            return;
        } catch (Throwable $e) {
            Log::error('Deep zoom tiling failed', ['photo' => $photo->slug, 'error' => $e->getMessage()]);
            $photo->markTilingFailed($e->getMessage());

            return;
        } finally {
            if ($deleteSource && is_file($source)) {
                @unlink($source);
            }

            if ($scratch) {
                File::deleteDirectory($scratch);
            }
        }

        if ($photo->is_zoomable && ! $disk->exists($relativeBase.'.dzi')) {
            $photo->markTilingFailed('vips finished but produced no .dzi descriptor.');

            return;
        }

        $photo->forceFill([
            'dzi_path' => $photo->is_zoomable ? $relativeBase.'.dzi' : null,
            'dzi_meta' => $dziMeta,
            'dzi_status' => Photo::TILING_READY,
            'dzi_stage' => null,
            'dzi_media_id' => $media->getKey(),
            'dzi_error' => null,
            'dzi_progress' => 100,
            'dzi_generated_at' => now(),
        ])->save();
    }

    /**
     * vips needs a real file. Spatie getPath() is that file on a local disk;
     * on R2 it is an object key, so the original is streamed to a temp file.
     *
     * @return array{0: string, 1: bool}
     */
    protected function materializeSource(Media $media): array
    {
        $path = $media->getPath();

        if (is_string($path) && is_file($path)) {
            return [$path, false];
        }

        $stream = Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot());

        if ($stream === false || $stream === null) {
            throw new RuntimeException('The uploaded file could not be found on disk.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'vips-src-');
        $out = fopen($tmp, 'wb');

        if ($out === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Could not create a local copy of the upload for vips.');
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [$tmp, true];
    }

    /**
     * @return array{0: string, 1: ?string} Absolute vips base path, and scratch dir to delete.
     */
    protected function vipsOutputBase(Filesystem $disk, string $relativeBase): array
    {
        if ($this->diskIsLocal($disk)) {
            $disk->makeDirectory(dirname($relativeBase));

            return [$disk->path($relativeBase), null];
        }

        $scratch = storage_path('app/vips-scratch/'.basename($relativeBase).'-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($scratch);

        return [$scratch.'/'.basename($relativeBase), $scratch];
    }

    protected function diskIsLocal(Filesystem $disk): bool
    {
        try {
            $probe = $disk->path('__local_probe__');
        } catch (Throwable) {
            return false;
        }

        return is_string($probe) && str_starts_with($probe, DIRECTORY_SEPARATOR);
    }

    /**
     * Uploads a vips scratch tree onto a remote disk, preserving relative paths.
     *
     * A gigapixel pyramid is a few thousand small files. Sending them one
     * round-trip at a time took longer than the tiling did, and when the job ran
     * out of time part way through it left a pyramid that still looked finished:
     * the .dzi descriptor was in place, so the viewer loaded it happily and then
     * 404ed on most of its tiles. So this uploads in parallel where the driver
     * allows it, and always finishes by listing what actually arrived.
     *
     * @param  array<string, true>  $skip  keys an earlier pass already uploaded
     * @param  Closure(int, int): void|null  $onProgress  files done, files expected
     * @return array<string, true>  every key now known to be on the disk
     */
    public function syncLocalTree(
        string $localDir,
        Filesystem $disk,
        string $prefix,
        array $skip = [],
        ?Closure $onProgress = null,
    ): array {
        $files = $this->collectTree($localDir, $prefix);
        $pending = array_diff_key($files, $skip);

        $failure = $this->putAll($pending, $disk, $onProgress);
        $missing = $this->missingFrom($files, $disk);

        // A handful of tiles lost to a transient error is normal on a few
        // thousand uploads, and re-sending only those is cheap.
        if ($missing !== []) {
            $failure ??= $this->putAll(array_intersect_key($files, $missing), $disk);
            $missing = $this->missingFrom($files, $disk);
        }

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                '%d of %d files did not reach storage under %s (first: %s)%s',
                count($missing),
                count($files),
                $prefix,
                array_key_first($missing),
                $failure ? '. Last upload error: '.$failure : '.',
            ));
        }

        return array_fill_keys(array_keys($files), true);
    }

    /**
     * Maps every file under a scratch directory to the disk key it belongs at.
     *
     * @return array<string, string> key => local path
     */
    protected function collectTree(string $localDir, string $prefix): array
    {
        $localDir = rtrim($localDir, DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDir, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace($localDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $files[trim($prefix.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative), '/')] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Which of these keys the disk does not hold.
     *
     * One listing per directory rather than one existence check per file: a
     * pyramid is thousands of tiles spread over a couple of dozen directories,
     * and a per-file check would cost as much as the upload.
     *
     * @param  array<string, string>  $files
     * @return array<string, true>
     */
    protected function missingFrom(array $files, Filesystem $disk): array
    {
        $byDirectory = [];

        foreach (array_keys($files) as $key) {
            $byDirectory[dirname($key)][] = $key;
        }

        $missing = [];

        foreach ($byDirectory as $directory => $keys) {
            $present = array_fill_keys($disk->files($directory), true);

            foreach ($keys as $key) {
                if (! isset($present[$key])) {
                    $missing[$key] = true;
                }
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $files  key => local path
     * @param  Closure(int, int): void|null  $onProgress
     * @return string|null  the first upload error, if any; callers verify regardless
     */
    protected function putAll(array $files, Filesystem $disk, ?Closure $onProgress = null): ?string
    {
        if ($files === []) {
            return null;
        }

        $total = count($files);
        $done = 0;

        // One row per file would be thousands of writes for a pyramid; the bar
        // cannot show more than about a percent of movement anyway.
        $tick = function () use (&$done, $total, $onProgress): void {
            $done++;

            if ($onProgress && ($done % 25 === 0 || $done === $total)) {
                $onProgress($done, $total);
            }
        };

        if (($pooled = $this->putAllPooled($files, $disk, $tick)) !== false) {
            return $pooled;
        }

        $failure = null;

        foreach ($files as $key => $path) {
            $stream = fopen($path, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Could not read '.$path.' for upload.');
            }

            try {
                $disk->put($key, $stream, [
                    'visibility' => 'private',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                    'ContentType' => $this->contentTypeFor(pathinfo($path, PATHINFO_EXTENSION)),
                ]);
            } catch (Throwable $e) {
                $failure ??= $e->getMessage();
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $tick();
            }
        }

        return $failure;
    }

    /**
     * Parallel upload through the S3 client the disk wraps, when it wraps one.
     *
     * @param  array<string, string>  $files
     * @param  Closure(): void  $tick  called once per finished file, ok or not
     * @return string|null|false  false when this disk has no S3 client to borrow
     */
    protected function putAllPooled(array $files, Filesystem $disk, Closure $tick): string|null|false
    {
        if (! method_exists($disk, 'getClient') || ! class_exists(CommandPool::class)) {
            return false;
        }

        $config = (array) config('filesystems.disks.'.config('gigapixel.disk'));
        $bucket = $config['bucket'] ?? null;

        if (! is_string($bucket) || $bucket === '') {
            return false;
        }

        $root = trim((string) ($config['root'] ?? ''), '/');
        $client = $disk->getClient();

        $commands = (function () use ($files, $client, $bucket, $root) {
            foreach ($files as $key => $path) {
                $stream = fopen($path, 'rb');

                if ($stream === false) {
                    throw new RuntimeException('Could not read '.$path.' for upload.');
                }

                yield $client->getCommand('PutObject', [
                    'Bucket' => $bucket,
                    'Key' => $root === '' ? $key : $root.'/'.$key,
                    'Body' => $stream,
                    'CacheControl' => 'public, max-age=31536000, immutable',
                    'ContentType' => $this->contentTypeFor(pathinfo($path, PATHINFO_EXTENSION)),
                ]);
            }
        })();

        $failure = null;

        // The pool returns errors rather than throwing them, which suits us: the
        // caller verifies the whole tree afterwards either way.
        $results = CommandPool::batch($client, $commands, [
            'concurrency' => self::UPLOAD_CONCURRENCY,
            'fulfilled' => fn () => $tick(),
            'rejected' => fn () => $tick(),
        ]);

        foreach ($results as $result) {
            if ($result instanceof Throwable) {
                $failure ??= $result->getMessage();
            }
        }

        return $failure;
    }

    protected function contentTypeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'dzi', 'xml' => 'application/xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            default => 'application/octet-stream',
        };
    }

    /**
     * Web-sized versions of an original too large for GD. `vips thumbnail` reads
     * only the resolution it needs, so this stays cheap even on a huge file.
     */
    protected function generateDerivatives(string $source, string $absoluteBase): void
    {
        $derivatives = (array) config('gigapixel.derivatives');
        $done = 0;

        $total = max(1, count($derivatives));

        foreach ($derivatives as $name => $longestEdge) {
            $this->report('derivatives', 'Making web-sized versions · '.$name, $done / $total);

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

            $this->report('derivatives', 'Making web-sized versions · '.$name, ++$done / $total);
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

    protected function runVips(string $source, string $absoluteBase): void
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

        $process->run(function (string $type, string $buffer) use (&$lastWritten): void {
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

            $this->report('slicing', 'Slicing tiles · '.min(100, $percent).'%', min(100, $percent) / 100);
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
