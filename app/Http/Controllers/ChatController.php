<?php

namespace App\Http\Controllers;

use App\Services\AnythingLLM\AnythingLLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        protected AnythingLLMService $anythingLLM
    ) {}

    public function index(): Response
    {
        try {
            $workspacesResponse = $this->anythingLLM->listWorkspaces();

            Log::info('Chat Index - Workspace Response Status: '.$workspacesResponse->status());
            Log::info('Chat Index - Workspace Response Body: '.$workspacesResponse->body());

            $workspaces = $workspacesResponse->successful()
                ? $workspacesResponse->json('workspaces', [])
                : [];

            Log::info('Chat Index - Workspaces Count: '.count($workspaces));
            Log::info('Chat Index - Workspaces: '.json_encode($workspaces));
        } catch (\Exception $e) {
            Log::warning('Chat Index - AnythingLLM connection failed: '.$e->getMessage());
            $workspaces = [];
        }

        return Inertia::render('chat/index', [
            'workspaces' => $workspaces,
            'defaultWorkspace' => ! empty($workspaces) ? $workspaces[0]['slug'] ?? null : null,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'workspace' => 'nullable|string|max:255',
            'mode' => 'nullable|string|in:chat,query',
        ]);

        $workspace = $validated['workspace'] ?? $this->getDefaultWorkspace();

        Log::info('Chat Send - Workspace: '.$workspace);
        Log::info('Chat Send - Message: '.$validated['message']);

        if (! $workspace) {
            return response()->json([
                'error' => 'No workspace available',
                'message' => 'Please ensure AnythingLLM is running and has at least one workspace configured.',
            ], 503);
        }

        $response = $this->anythingLLM->chat(
            slug: $workspace,
            message: $validated['message'],
            mode: $validated['mode'] ?? 'chat'
        );

        Log::info('Chat Send - Response Status: '.$response->status());
        Log::info('Chat Send - Response Body: '.$response->body());

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to get AI response',
                'message' => 'The AI service is currently unavailable. Please try again later.',
                'details' => $response->body(),
            ], $response->status());
        }

        $data = $response->json();

        // Check for error or abort responses from AnythingLLM
        if (isset($data['type']) && $data['type'] === 'abort') {
            Log::error('Chat Send - AnythingLLM Error: '.($data['error'] ?? 'Unknown error'));

            return response()->json([
                'error' => 'Configuration error',
                'message' => $data['error'] ?? 'The AI workspace is not properly configured. Please check AnythingLLM settings.',
            ], 500);
        }

        return response()->json([
            'message' => $data['textResponse'] ?? 'No response received',
            'timestamp' => now()->toIso8601String(),
            'workspace' => $workspace,
            'type' => $data['type'] ?? 'chat',
        ]);
    }

    private function getDefaultWorkspace(): ?string
    {
        $response = $this->anythingLLM->listWorkspaces();

        if ($response->failed()) {
            return null;
        }

        $workspaces = $response->json('workspaces', []);

        return ! empty($workspaces) ? $workspaces[0]['slug'] ?? null : null;
    }
}
