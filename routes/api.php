<?php

use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\WorkshopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
|
| The portfolio is a static SPA with no accounts and no login, so everything
| here is unauthenticated: reads are public, and the single write is throttled
| to keep the open reservation endpoint from being abused.
|
*/

Route::get('workshops', [WorkshopController::class, 'index']);
Route::get('workshops/{workshop}', [WorkshopController::class, 'show']);

Route::post('workshops/{workshop}/reservations', [ReservationController::class, 'store'])
    ->middleware('throttle:reservations');

/*
| Content — everything the SPA used to hardcode in src/data/*.js.
*/

Route::get('site', [ContentController::class, 'site']);
Route::get('about', [ContentController::class, 'about']);
Route::get('hero-slides', [ContentController::class, 'heroSlides']);
Route::get('categories', [ContentController::class, 'categories']);
Route::get('photos', [ContentController::class, 'photos']);
Route::get('posts', [ContentController::class, 'posts']);
Route::get('posts/{post}', [ContentController::class, 'post']);
