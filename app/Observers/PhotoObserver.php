<?php

namespace App\Observers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

class PhotoObserver
{
    /**
     * Covers the case the upload listener does not: ticking "deep zoom" on a photo
     * whose image was uploaded some time ago.
     */
    public function saved(Photo $photo): void
    {
        if ($photo->wasChanged('is_zoomable') || $photo->wasRecentlyCreated) {
            Photo::queueTilingFor($photo);
        }
    }

    /** Tiles are worthless without their photo, and there are thousands of them. */
    public function deleted(Photo $photo): void
    {
        if (blank($photo->dzi_path)) {
            return;
        }

        $disk = Storage::disk(config('gigapixel.disk'));
        $base = preg_replace('/\.dzi$/', '', $photo->dzi_path);

        $disk->delete($photo->dzi_path);
        $disk->deleteDirectory($base.'_files');
    }
}
