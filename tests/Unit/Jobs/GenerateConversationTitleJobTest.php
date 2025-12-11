<?php

use App\Jobs\GenerateConversationTitleJob;
use App\Models\Conversation;
use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\LLM\DTOs\ChatResponse;
use App\Services\LLM\LLMManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('generates title for new conversation', function () {
    $user = User::factory()->create();
    LLMIntegration::factory()->create([
        'user_id' => $user->id,
        'active_integration' => 'openai',
        'active_model' => 'gpt-4',
        'integration_status' => 'online',
    ]);

    $conversation = Conversation::factory()->create([
        'user_id' => $user->id,
        'title' => 'New Conversation',
    ]);

    // Add messages because the job checks for empty messages
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi there']);

    $mockLLMManager = Mockery::mock(LLMManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(content: 'Greeting Chat'));
    });

    $job = new GenerateConversationTitleJob($conversation, $user);
    $job->handle($mockLLMManager);

    expect($conversation->fresh()->title)->toBe('Greeting Chat');
});

test('skips when title is not default', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'user_id' => $user->id,
        'title' => 'Existing Title',
    ]);

    $mockLLMManager = Mockery::mock(LLMManager::class);
    $mockLLMManager->shouldNotReceive('chat');

    $job = new GenerateConversationTitleJob($conversation, $user);
    $job->handle($mockLLMManager);

    expect($conversation->fresh()->title)->toBe('Existing Title');
});

test('skips when no messages exist', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'user_id' => $user->id,
        'title' => 'New Conversation',
    ]);

    $mockLLMManager = Mockery::mock(LLMManager::class);
    $mockLLMManager->shouldNotReceive('chat');

    $job = new GenerateConversationTitleJob($conversation, $user);
    $job->handle($mockLLMManager);

    expect($conversation->fresh()->title)->toBe('New Conversation');
});
