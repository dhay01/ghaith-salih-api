<?php

namespace App\Listeners;

use App\Jobs\GenerateDeepZoomTiles;
use App\Models\Photo;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Filament attaches the uploaded file after the record is saved, so a model
 * observer alone fires too early to see it. This catches the upload itself.
 */
class QueueDeepZoomTiling
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $model = $event->media->model;

        if (! $model instanceof Photo) {
            return;
        }

        if ($event->media->collection_name !== 'image') {
            return;
        }

        Photo::queueTilingFor($model->fresh());
    }
}
