<?php

use App\Http\Controllers\AnalysisController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// URL expansion for Amazon short URLs
Route::post('/expand-url', [App\Http\Controllers\UrlExpansionController::class, 'expandUrl']);

// Async analysis endpoints - require session and CSRF protection
Route::prefix('analysis')->name('api.analysis.')
    ->middleware(['web']) // Use web middleware for session support
    ->group(function () {
        Route::post('/start', [AnalysisController::class, 'startAnalysis'])
            ->middleware(['throttle:30,1']) // 30 requests per minute per IP
            ->name('start');
        Route::get('/progress/{sessionId}', [AnalysisController::class, 'getProgress'])->name('progress');
        Route::delete('/cancel/{sessionId}', [AnalysisController::class, 'cancelAnalysis'])->name('cancel');
        Route::post('/cleanup', [AnalysisController::class, 'cleanup'])->name('cleanup');
    });

// Chrome Extension API endpoints - use API key authentication
Route::prefix('extension')->name('api.extension.')
    ->group(function () {
        // Review submission with rate limiting to prevent abuse
        Route::post('/submit-reviews', [App\Http\Controllers\ExtensionController::class, 'submitReviews'])
            ->middleware(['throttle:60,1']) // 60 requests per minute per IP
            ->name('submit');

        // Status endpoint without rate limiting for frequent polling
        Route::get('/analysis/{asin}/{country}', [App\Http\Controllers\ExtensionController::class, 'getAnalysisStatus'])->name('status');

        // Progress endpoint for async analysis tracking
        Route::get('/progress/{sessionId}', [App\Http\Controllers\ExtensionController::class, 'getExtensionProgress'])->name('progress');
    });

// Chrome Extension Legacy API endpoint - alias for extension endpoint
// Some extension versions call this URL pattern
Route::get('/chrome-extension/check-analysis/{asin}/{country}', [App\Http\Controllers\ExtensionController::class, 'getAnalysisStatus'])
    ->name('api.chrome-extension.check-analysis');
