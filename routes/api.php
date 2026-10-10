<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('envelope', [IngestController::class, 'envelope'])->name('api.envelope');
    Route::post('store', [IngestController::class, 'envelope']);
    Route::post('browser', [IngestController::class, 'browser'])->name('api.browser');
    Route::get('health', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));
});
