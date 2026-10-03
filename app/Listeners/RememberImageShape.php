<?php

namespace App\Listeners;

use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Throwable;

/**
 * Measures an upload as soon as its thumbnail exists. It is never allowed to
 * fail the upload: a frame with no recorded shape falls back to its typed ratio.
 */
class RememberImageShape
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        if ($event->conversion->getName() !== 'thumb') {
            return;
        }

        $model = $event->media->model;

        if (! $model || ! method_exists($model, 'rememberShapeOf')) {
            return;
        }

        try {
            $model->rememberShapeOf($event->media);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
