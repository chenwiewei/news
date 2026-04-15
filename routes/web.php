<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;

Route::get('/', [VideoController::class, 'index'])->name('video.index');
Route::get('/video/create', [VideoController::class, 'create'])->name('video.create');
Route::post('/video/store', [VideoController::class, 'store'])->name('video.store');
Route::get('/video/generate/{encodedPath}', [VideoController::class, 'generate'])->name('video.generate')->where('encodedPath', '.*');
Route::get('/video/download/{encodedPath}', [VideoController::class, 'download'])->name('video.download')->where('encodedPath', '.*');
