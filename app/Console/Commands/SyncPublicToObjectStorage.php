<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** One-shot copy of the local public disk onto the S3/R2 disk. */
class SyncPublicToObjectStorage extends Command
{
    protected $signature = 'media:sync-to-object-storage
                            {--promote : Point existing media rows at the s3 disk}';

    protected $description = 'Copy storage/app/public onto the s3 disk (R2).';

    public function handle(): int
    {
        $local = Storage::disk('public');
        $remote = Storage::disk('s3');
        $files = $local->allFiles();
        $copied = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($remote->exists($file)) {
                $skipped++;

                continue;
            }

            $stream = $local->readStream($file);

            if ($stream === false) {
                $this->error('Could not read '.$file);

                return self::FAILURE;
            }

            $remote->put($file, $stream, [
                'visibility' => 'private',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $copied++;
            $this->line($file);
        }

        $this->info("copied {$copied}, already present {$skipped}, total ".count($files));

        if ($this->option('promote')) {
            $updated = DB::table('media')->where('disk', 'public')->update([
                'disk' => 's3',
                'conversions_disk' => 's3',
            ]);
            $this->info("media rows promoted: {$updated}");
        }

        return self::SUCCESS;
    }
}
