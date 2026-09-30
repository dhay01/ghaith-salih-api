<?php

namespace App\Providers;

use App\Listeners\QueueDeepZoomTiling;
use App\Models\Photo;
use App\Observers\PhotoObserver;
use Illuminate\Support\Facades\Event;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Photo::observe(PhotoObserver::class);
        Event::listen(MediaHasBeenAddedEvent::class, QueueDeepZoomTiling::class);

        // Livewire refuses any temporary upload over 12 MB unless told otherwise, yet
        // every image field in the dashboard advertises gigapixel.large_file_bytes.
        // A photo between the two passes the field's own check, is rejected by the
        // server, and the record saves without its picture. Only `rules` is set so
        // the rest of Livewire's upload config keeps its defaults.
        config()->set('livewire.temporary_file_upload.rules', [
            'required',
            'file',
            'max:'.(int) (config('gigapixel.large_file_bytes') / 1024),
        ]);

        // The reservation endpoint is public and unauthenticated, so it is
        // throttled per IP: generous enough for a genuine applicant who
        // mistypes and retries, tight enough to make scripted spam pointless.
        RateLimiter::for('reservations', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);
    }
}
