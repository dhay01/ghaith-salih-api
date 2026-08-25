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

        // The reservation endpoint is public and unauthenticated, so it is
        // throttled per IP: generous enough for a genuine applicant who
        // mistypes and retries, tight enough to make scripted spam pointless.
        RateLimiter::for('reservations', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);
    }
}
