<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Shapes are measured on upload; this covers images uploaded before that, and
 * any whose measurement failed at the time.
 */
class RememberImageShapes extends Command
{
    protected $signature = 'media:remember-shapes {--all : Measure again where a shape is already recorded}';

    protected $description = "Record each uploaded image's proportions so galleries can show it at its real shape";

    public function handle(): int
    {
        $measured = 0;

        Media::query()->each(function (Media $media) use (&$measured): void {
            if (! $this->option('all') && $media->hasCustomProperty('ratio')) {
                return;
            }

            $model = $media->model;

            if (! $model || ! method_exists($model, 'rememberShapeOf')) {
                return;
            }

            try {
                $model->rememberShapeOf($media);
            } catch (Throwable $e) {
                $this->warn("Could not measure media {$media->getKey()}: {$e->getMessage()}");

                return;
            }

            if ($media->hasCustomProperty('ratio')) {
                $measured++;
            }
        });

        $this->info("Measured {$measured} image(s).");

        return self::SUCCESS;
    }
}
