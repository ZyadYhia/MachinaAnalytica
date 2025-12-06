<?php

use App\Jobs\ProcessUnifiedChatJob;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\LLMIntegration;
use App\Models\User;
use App\Services\LLM\DTOs\ChatResponse;
use App\Services\LLM\LLMManager;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->integration = LLMIntegration::factory()->for($this->user)->create([
        'active_integration' => 'jan',
        'active_model' => 'llama3-8b',
        'chat_mode' => 'sync',
    ]);
});

it('sends a chat message in sync mode', function () {
    $manager = mock(LLMManager::class);
    $manager->shouldReceive('chat')
        ->once()
        ->andReturn(new ChatResponse(
            content: 'Test response',
            model: 'llama3-8b',
            finishReason: 'stop',
            usage: ['tokens' => 100]
        ));

    actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'Hello, how are you?',
        ])
        ->assertOk()
        ->assertJson([
            'content' => 'Test response',
            'model' => 'llama3-8b',
        ]);

    assertDatabaseHas('conversations', [
        'user_id' => $this->user->id,
        'provider' => 'jan',
        'model' => 'llama3-8b',
    ]);

    assertDatabaseHas('chat_messages', [
        'role' => 'user',
        'content' => 'Hello, how are you?',
    ]);

    assertDatabaseHas('chat_messages', [
        'role' => 'assistant',
        'content' => 'Test response',
    ]);
});

it('dispatches job for async chat mode', function () {
    Queue::fake();

    $this->integration->update(['chat_mode' => 'async']);

    actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'Async message',
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'processing',
        ]);

    Queue::assertPushed(ProcessUnifiedChatJob::class, function ($job) {
        return $job->userMessage->content === 'Async message';
    });
});

it('creates new conversation if none provided', function () {
    $manager = mock(LLMManager::class);
    $manager->shouldReceive('chat')
        ->once()
        ->andReturn(new ChatResponse(
            content: 'Response',
            model: 'llama3-8b',
            finishReason: 'stop'
        ));

    $response = actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'New conversation',
        ])
        ->assertOk();

    expect($response->json('conversation_id'))->toBeInt();

    assertDatabaseHas('conversations', [
        'user_id' => $this->user->id,
        'title' => 'New conversation',
    ]);
});

it('uses existing conversation if provided', function () {
    $conversation = Conversation::factory()->for($this->user)->create([
        'provider' => 'jan',
        'model' => 'llama3-8b',
    ]);

    $manager = mock(LLMManager::class);
    $manager->shouldReceive('chat')
        ->once()
        ->andReturn(new ChatResponse(
            content: 'Response',
            model: 'llama3-8b',
            finishReason: 'stop'
        ));

    actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'Continue conversation',
            'conversation_id' => $conversation->id,
        ])
        ->assertOk()
        ->assertJson([
            'conversation_id' => $conversation->id,
        ]);
});

it('validates message is required', function () {
    actingAs($this->user)
        ->postJson('/unified-chat/chat', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('validates conversation belongs to user', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->for($otherUser)->create();

    actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'Test',
            'conversation_id' => $conversation->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['conversation_id']);
});

it('requires active integration to chat', function () {
    $this->integration->delete();

    actingAs($this->user)
        ->postJson('/unified-chat/chat', [
            'message' => 'Test',
        ])
        ->assertStatus(400)
        ->assertJson([
            'message' => 'No active LLM integration configured. Please configure your integration in settings.',
        ]);
});

it('lists user conversations', function () {
    $conversations = Conversation::factory()
        ->for($this->user)
        ->count(3)
        ->create();

    actingAs($this->user)
        ->get('/unified-chat/conversations')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([
            '*' => ['id', 'title', 'provider', 'model', 'created_at'],
        ]);
});

it('shows conversation with messages', function () {
    $conversation = Conversation::factory()->for($this->user)->create();
    ChatMessage::factory()->for($conversation)->count(5)->create();

    actingAs($this->user)
        ->get("/unified-chat/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJson([
            'id' => $conversation->id,
        ])
        ->assertJsonCount(5, 'messages');
});

it('creates a new conversation', function () {
    actingAs($this->user)
        ->postJson('/unified-chat/conversations', [
            'title' => 'My New Chat',
        ])
        ->assertCreated();

    assertDatabaseHas('conversations', [
        'user_id' => $this->user->id,
        'title' => 'My New Chat',
        'provider' => 'jan',
        'model' => 'llama3-8b',
    ]);
});

it('deletes a conversation', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    actingAs($this->user)
        ->delete("/unified-chat/conversations/{$conversation->id}")
        ->assertNoContent();

    expect($conversation->fresh()->trashed())->toBeTrue();
});

it('prevents deleting other users conversations', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->for($otherUser)->create();

    actingAs($this->user)
        ->delete("/unified-chat/conversations/{$conversation->id}")
        ->assertForbidden();
});

it('requires authentication for all chat endpoints', function () {
    postJson('/unified-chat/chat')->assertUnauthorized();
    get('/unified-chat/conversations')->assertRedirect('/login');
    get('/unified-chat/conversations/1')->assertRedirect('/login');
    postJson('/unified-chat/conversations')->assertUnauthorized();
    delete('/unified-chat/conversations/1')->assertRedirect('/login');
});
