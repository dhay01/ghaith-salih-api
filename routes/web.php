<?php

use App\Http\Controllers\Admin\ChunkedUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Large uploads for the dashboard. Behind session auth and an is_admin check in
| the controller — these accept files, so they are never public.
*/
Route::middleware(['web', 'auth'])
    ->prefix('admin/large-upload')
    ->name('large-upload.')
    ->group(function () {
        Route::post('chunk', [ChunkedUploadController::class, 'store'])->name('chunk');
        Route::post('finish', [ChunkedUploadController::class, 'finish'])->name('finish');
    });
