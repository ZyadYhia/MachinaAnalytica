<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\UnifiedChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('integration', function () {
        return Inertia::render('integration/selector');
    })->name('integration.selector');

    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
    });

    // Unified chat routes (provider-agnostic)
    Route::prefix('unified-chat')->name('unified-chat.')->group(function () {
        Route::post('/', [UnifiedChatController::class, 'chat'])->name('send');
        Route::get('/conversations', [UnifiedChatController::class, 'listConversations'])->name('conversations.list');
        Route::get('/conversations/{conversationId}', [UnifiedChatController::class, 'getConversation'])->name('conversations.get');
        Route::delete('/conversations/{conversationId}', [UnifiedChatController::class, 'deleteConversation'])->name('conversations.delete');
    });


});

require __DIR__ . '/settings.php';
