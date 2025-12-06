<?php

use App\Http\Controllers\AnythingLLMController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\JanController;
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
        Route::post('/send', [ChatController::class, 'send'])->name('send');
    });

    Route::prefix('anythingllm')->name('anythingllm.')->group(function () {
        Route::get('/', [AnythingLLMController::class, 'index'])->name('index');
        Route::get('/check-auth', [AnythingLLMController::class, 'checkAuth'])->name('check-auth');

        // Workspace management
        Route::get('/workspaces', [AnythingLLMController::class, 'workspaces'])->name('workspaces');
        Route::post('/workspaces', [AnythingLLMController::class, 'createWorkspace'])->name('workspaces.create');
        Route::get('/workspace/{slug}', [AnythingLLMController::class, 'workspace'])->name('workspace');
        Route::patch('/workspace/{slug}', [AnythingLLMController::class, 'updateWorkspace'])->name('workspace.update');
        Route::delete('/workspace/{slug}', [AnythingLLMController::class, 'deleteWorkspace'])->name('workspace.delete');

        // Workspace operations
        Route::post('/chat', [AnythingLLMController::class, 'chat'])->name('chat');
        Route::post('/workspace/{slug}/vector-search', [AnythingLLMController::class, 'vectorSearch'])->name('vector-search');
        Route::get('/workspace/{slug}/chats', [AnythingLLMController::class, 'chats'])->name('chats');

        // Thread management
        Route::post('/workspace/{slug}/threads', [AnythingLLMController::class, 'createThread'])->name('threads.create');
        Route::patch('/workspace/{slug}/thread/{threadSlug}', [AnythingLLMController::class, 'updateThread'])->name('thread.update');
        Route::delete('/workspace/{slug}/thread/{threadSlug}', [AnythingLLMController::class, 'deleteThread'])->name('thread.delete');
        Route::get('/workspace/{slug}/thread/{threadSlug}/chats', [AnythingLLMController::class, 'threadChats'])->name('thread.chats');
        Route::post('/workspace/{slug}/thread/{threadSlug}/chat', [AnythingLLMController::class, 'chatWithThread'])->name('thread.chat');

        // Documents
        Route::get('/documents', [AnythingLLMController::class, 'documents'])->name('documents');
    });

    Route::prefix('jan')->name('jan.')->group(function () {
        Route::get('/', [JanController::class, 'index'])->name('index');
        Route::get('/check-connection', [JanController::class, 'checkConnection'])->name('check-connection');
        Route::get('/models', [JanController::class, 'models'])->name('models');

        // Chat with MCP tools integration
        Route::post('/chat', [JanController::class, 'chat'])
            ->middleware('jan.mcp')
            ->name('chat');

        // Chat with conversation history
        Route::post('/chat/history', [JanController::class, 'chatWithHistory'])
            ->middleware('jan.mcp')
            ->name('chat.history');
        Route::get('/conversation/{conversationId?}', [JanController::class, 'getConversation'])->name('conversation.get');
        Route::delete('/conversation/{conversationId?}', [JanController::class, 'clearConversation'])->name('conversation.clear');

        // MCP Tools management endpoints
        Route::prefix('mcp')->name('mcp.')->group(function () {
            Route::get('/tools', [JanController::class, 'tools'])->name('tools');
            Route::get('/tools/{toolName}', [JanController::class, 'tool'])->name('tool');
            Route::post('/cache/clear', [JanController::class, 'clearCache'])->name('cache.clear');
            Route::get('/status', [JanController::class, 'status'])->name('status');
        });

        // Health check
        Route::get('/health', [JanController::class, 'health'])->name('health');
    });
});

require __DIR__.'/settings.php';
