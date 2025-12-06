<?php

namespace App\Services\AnythingLLM;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AnythingLLMService
{
    public function __construct(
        protected string $baseUrl,
        protected string $authToken
    ) {}

    /**
     * Get configured HTTP client with base URL and authorization.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->authToken,
                'Accept' => 'application/json',
            ])
            ->timeout(30);
    }

    /**
     * List all workspaces.
     */
    public function listWorkspaces(): Response
    {
        return $this->client()->get('v1/workspaces');
    }

    /**
     * Get a specific workspace by slug.
     */
    public function getWorkspace(string $slug): Response
    {
        return $this->client()->get("v1/workspace/{$slug}");
    }

    /**
     * Create a new workspace.
     */
    public function createWorkspace(string $name): Response
    {
        return $this->client()->post('v1/workspace/new', [
            'name' => $name,
        ]);
    }

    /**
     * Update workspace settings.
     */
    public function updateWorkspace(string $slug, array $data): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/update", $data);
    }

    /**
     * Delete a workspace.
     */
    public function deleteWorkspace(string $slug): Response
    {
        return $this->client()->delete("v1/workspace/{$slug}");
    }

    /**
     * Send a chat message to a workspace (non-streamed).
     */
    public function chat(string $slug, string $message, ?string $mode = null): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/chat", [
            'message' => $message,
            'mode' => $mode ?? 'chat',
        ]);
    }

    /**
     * Send a chat message to a workspace (streamed).
     */
    public function streamChat(string $slug, string $message, ?string $mode = null): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/stream-chat", [
            'message' => $message,
            'mode' => $mode ?? 'chat',
        ]);
    }

    /**
     * List chats in a workspace.
     */
    public function listChats(string $slug): Response
    {
        return $this->client()->get("v1/workspace/{$slug}/chats");
    }

    /**
     * Perform a vector search within a workspace.
     */
    public function vectorSearch(string $slug, string $query): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/vector-search", [
            'query' => $query,
        ]);
    }

    /**
     * Update embeddings for documents in a workspace.
     */
    public function updateEmbeddings(string $slug, array $adds = [], array $deletes = []): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/update-embeddings", [
            'adds' => $adds,
            'deletes' => $deletes,
        ]);
    }

    /**
     * List all documents.
     */
    public function listDocuments(): Response
    {
        return $this->client()->get('v1/documents');
    }

    /**
     * List documents in a specific folder.
     */
    public function listDocumentsInFolder(string $folderName): Response
    {
        return $this->client()->get("v1/documents/folder/{$folderName}");
    }

    /**
     * Get document metadata by name.
     */
    public function getDocument(string $docName): Response
    {
        return $this->client()->get("v1/document/{$docName}");
    }

    /**
     * Create a new document folder.
     */
    public function createFolder(string $name): Response
    {
        return $this->client()->post('v1/document/create-folder', [
            'name' => $name,
        ]);
    }

    /**
     * Remove a document folder.
     */
    public function removeFolder(string $name): Response
    {
        return $this->client()->delete('v1/document/remove-folder', [
            'name' => $name,
        ]);
    }

    /**
     * Get system information.
     */
    public function getSystemInfo(): Response
    {
        return $this->client()->get('v1/system');
    }

    /**
     * Get total vector count.
     */
    public function getVectorCount(): Response
    {
        return $this->client()->get('v1/system/vector-count');
    }

    /**
     * Check basic authentication status.
     */
    public function checkAuth(): Response
    {
        return $this->client()->get('v1/auth');
    }

    /**
     * Create a new thread in a workspace.
     */
    public function createThread(string $slug, string $name): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/thread/new", [
            'name' => $name,
        ]);
    }

    /**
     * Update a thread.
     */
    public function updateThread(string $slug, string $threadSlug, array $data): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/thread/{$threadSlug}/update", $data);
    }

    /**
     * Delete a thread.
     */
    public function deleteThread(string $slug, string $threadSlug): Response
    {
        return $this->client()->delete("v1/workspace/{$slug}/thread/{$threadSlug}");
    }

    /**
     * List chats in a specific thread.
     */
    public function listThreadChats(string $slug, string $threadSlug): Response
    {
        return $this->client()->get("v1/workspace/{$slug}/thread/{$threadSlug}/chats");
    }

    /**
     * Send a chat message to a thread (non-streamed).
     */
    public function chatWithThread(string $slug, string $threadSlug, string $message, ?string $mode = null): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/thread/{$threadSlug}/chat", [
            'message' => $message,
            'mode' => $mode ?? 'chat',
        ]);
    }

    /**
     * Send a chat message to a thread (streamed).
     */
    public function streamChatWithThread(string $slug, string $threadSlug, string $message, ?string $mode = null): Response
    {
        return $this->client()->post("v1/workspace/{$slug}/thread/{$threadSlug}/stream-chat", [
            'message' => $message,
            'mode' => $mode ?? 'chat',
        ]);
    }
}
