<?php

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
