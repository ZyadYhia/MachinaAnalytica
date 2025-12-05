<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatMessageRequest;
use App\Services\AnythingLLM\AnythingLLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnythingLLMController extends Controller
{
    public function __construct(
        protected AnythingLLMService $anythingLLM
    ) {}

    /**
     * Display the chat interface.
     */
    public function index(): Response
    {
        $workspaces = $this->anythingLLM->listWorkspaces();

        return Inertia::render('AnythingLLM/Chat', [
            'workspaces' => $workspaces->successful() ? $workspaces->json('workspaces', []) : [],
        ]);
    }

    /**
     * List all workspaces.
     */
    public function workspaces(): JsonResponse
    {
        $response = $this->anythingLLM->listWorkspaces();

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch workspaces',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Create a new workspace.
     */
    public function createWorkspace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $response = $this->anythingLLM->createWorkspace($validated['name']);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to create workspace',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json(), 201);
    }

    /**
     * Update a workspace.
     */
    public function updateWorkspace(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'openAiTemp' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'openAiHistory' => ['sometimes', 'integer', 'min:0'],
            'similarityThreshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'topN' => ['sometimes', 'integer', 'min:1'],
        ]);

        $response = $this->anythingLLM->updateWorkspace($slug, $validated);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to update workspace',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Delete a workspace.
     */
    public function deleteWorkspace(string $slug): JsonResponse
    {
        $response = $this->anythingLLM->deleteWorkspace($slug);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to delete workspace',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Get a specific workspace.
     */
    public function workspace(string $slug): JsonResponse
    {
        $response = $this->anythingLLM->getWorkspace($slug);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch workspace',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Send a chat message to a workspace.
     */
    public function chat(ChatMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $response = $this->anythingLLM->chat(
            slug: $validated['workspace_slug'],
            message: $validated['message'],
            mode: $validated['mode'] ?? 'chat'
        );

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to send message',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Perform a vector search within a workspace.
     */
    public function vectorSearch(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
        ]);

        $response = $this->anythingLLM->vectorSearch($slug, $validated['query']);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to perform vector search',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * List chats in a workspace.
     */
    public function chats(string $slug): JsonResponse
    {
        $response = $this->anythingLLM->listChats($slug);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch chats',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * List all documents.
     */
    public function documents(): JsonResponse
    {
        $response = $this->anythingLLM->listDocuments();

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch documents',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Check authentication with AnythingLLM.
     */
    public function checkAuth(): JsonResponse
    {
        $response = $this->anythingLLM->checkAuth();

        return response()->json([
            'authenticated' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful() ? 'Authentication successful' : 'Authentication failed',
        ]);
    }

    /**
     * Create a new thread in a workspace.
     */
    public function createThread(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $response = $this->anythingLLM->createThread($slug, $validated['name']);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to create thread',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json(), 201);
    }

    /**
     * Update a thread.
     */
    public function updateThread(Request $request, string $slug, string $threadSlug): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $response = $this->anythingLLM->updateThread($slug, $threadSlug, $validated);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to update thread',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Delete a thread.
     */
    public function deleteThread(string $slug, string $threadSlug): JsonResponse
    {
        $response = $this->anythingLLM->deleteThread($slug, $threadSlug);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to delete thread',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * List chats in a specific thread.
     */
    public function threadChats(string $slug, string $threadSlug): JsonResponse
    {
        $response = $this->anythingLLM->listThreadChats($slug, $threadSlug);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch thread chats',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Send a chat message to a thread.
     */
    public function chatWithThread(Request $request, string $slug, string $threadSlug): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'mode' => ['nullable', 'string', 'in:chat,query'],
        ]);

        $response = $this->anythingLLM->chatWithThread(
            slug: $slug,
            threadSlug: $threadSlug,
            message: $validated['message'],
            mode: $validated['mode'] ?? 'chat'
        );

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to send message to thread',
                'message' => $response->body(),
            ], $response->status());
        }

        return response()->json($response->json());
    }
}
