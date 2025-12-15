<?php

use App\Http\Controllers\API\ML\CompressorDataController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UnifiedChatController;
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
// ... (Existing External Ingestion Route) ...
Route::post('/compressor/data', [CompressorDataController::class, 'processNewData'])->name('compressor.ingest');


// Unified chat routes (provider-agnostic, authenticated with session)
Route::middleware(['web', 'auth:web'])->prefix('unified-chat')->name('unified-chat.')->group(function () {
    Route::post('/', [UnifiedChatController::class, 'chat'])->name('send');
    Route::get('/conversations', [UnifiedChatController::class, 'listConversations'])->name('conversations.list');
    Route::get('/conversations/{conversationId}', [UnifiedChatController::class, 'getConversation'])->name('conversations.get');
    Route::delete('/conversations/{conversationId}', [UnifiedChatController::class, 'deleteConversation'])->name('conversations.delete');
});

// --- NEW ML INTERNAL ROUTES (Protected by Middleware) ---

// 1. ML fetches the target record and history
Route::middleware('check.ml.token')->get('/ml/data/fetch_for_analysis', [CompressorDataController::class, 'fetchForAnalysis'])
    ->name('ml.fetch.analysis');

// 2. ML pushes the final status back to Laravel
Route::middleware('check.ml.token')->post('/ml/data/update_status', [CompressorDataController::class, 'updateStatus'])
    ->name('ml.update.status');
