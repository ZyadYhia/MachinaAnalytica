<?php

use App\Services\AnythingLLM\AnythingLLMService;
use App\Services\Jan\JanService;
use App\Services\LLM\DTOs\ChatRequest;
use App\Services\LLM\DTOs\Message;
use App\Services\LLM\Providers\AnythingLLMProvider;
use App\Services\LLM\Providers\JanProvider;
use Illuminate\Http\Client\Response;

use function Pest\Laravel\mock;

describe('JanProvider', function () {
    beforeEach(function () {
        $this->janService = mock(JanService::class);
        $this->provider = new JanProvider($this->janService);
    });

    it('can be instantiated', function () {
        expect($this->provider)->toBeInstanceOf(JanProvider::class);
    });

    it('checks health via Jan service', function () {
        $this->janService->shouldReceive('checkConnection')
            ->once()
            ->andReturn(true);

        $health = $this->provider->checkHealth();

        expect($health)->toBeTrue();
    });

    it('returns false when health check fails', function () {
        $this->janService->shouldReceive('checkConnection')
            ->once()
            ->andReturn(false);

        $health = $this->provider->checkHealth();

        expect($health)->toBeFalse();
    });

    it('lists models from Jan service', function () {
        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'data' => [
                ['id' => 'model-1', 'name' => 'Model 1', 'owned_by' => 'jan'],
                ['id' => 'model-2', 'name' => 'Model 2', 'owned_by' => 'jan'],
            ],
        ]);

        $this->janService->shouldReceive('listModels')
            ->once()
            ->andReturn($mockResponse);

        $models = $this->provider->listModels();

        expect($models)->toBeArray()
            ->toHaveCount(2)
            ->and($models[0])->toHaveKey('id', 'model-1')
            ->and($models[1])->toHaveKey('id', 'model-2');
    });

    it('returns empty array when listing models fails', function () {
        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(false);

        $this->janService->shouldReceive('listModels')
            ->once()
            ->andReturn($mockResponse);

        $models = $this->provider->listModels();

        expect($models)->toBeArray()->toBeEmpty();
    });

    it('sends chat request to Jan service', function () {
        $request = new ChatRequest(
            messages: [
                new Message(role: 'user', content: 'Hello'),
            ],
            model: 'llama3-8b'
        );

        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hi there!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'model' => 'llama3-8b',
            'usage' => ['total_tokens' => 50],
        ]);

        $this->janService->shouldReceive('chat')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->provider->chat($request);

        expect($response->content)->toBe('Hi there!')
            ->and($response->model)->toBe('llama3-8b')
            ->and($response->finishReason)->toBe('stop');
    });
});

describe('AnythingLLMProvider', function () {
    beforeEach(function () {
        $this->anythingService = mock(AnythingLLMService::class);
        $this->provider = new AnythingLLMProvider($this->anythingService);
    });

    it('can be instantiated', function () {
        expect($this->provider)->toBeInstanceOf(AnythingLLMProvider::class);
    });

    it('checks health via AnythingLLM service', function () {
        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(true);

        $this->anythingService->shouldReceive('listWorkspaces')
            ->once()
            ->andReturn($mockResponse);

        $health = $this->provider->checkHealth();

        expect($health)->toBeTrue();
    });

    it('returns false when health check fails', function () {
        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->once()->andReturn(false);

        $this->anythingService->shouldReceive('listWorkspaces')
            ->once()
            ->andReturn($mockResponse);

        $health = $this->provider->checkHealth();

        expect($health)->toBeFalse();
    });

    it('lists workspaces as models', function () {
        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'workspaces' => [
                ['slug' => 'workspace-1', 'name' => 'Workspace 1'],
                ['slug' => 'workspace-2', 'name' => 'Workspace 2'],
            ],
        ]);

        $this->anythingService->shouldReceive('listWorkspaces')
            ->once()
            ->andReturn($mockResponse);

        $models = $this->provider->listModels();

        expect($models)->toBeArray()
            ->toHaveCount(2)
            ->and($models[0])->toHaveKey('id', 'workspace-1')
            ->and($models[0])->toHaveKey('name', 'Workspace 1');
    });

    it('sends chat request to AnythingLLM service', function () {
        $request = new ChatRequest(
            messages: [
                new Message(role: 'user', content: 'Hello'),
            ],
            model: 'workspace-1'
        );

        $mockResponse = mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->andReturn([
            'textResponse' => 'Hello from AnythingLLM!',
            'type' => 'textResponse',
        ]);

        $this->anythingService->shouldReceive('chat')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->provider->chat($request);

        expect($response->content)->toBe('Hello from AnythingLLM!')
            ->and($response->model)->toBe('workspace-1');
    });
});
