<?php

namespace App\Services\Agent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentService
{
    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 300
    ) {}

    /**
     * Send a chat request to the Agent
     */
    public function chat(array $payload): array
    {
        $url = rtrim($this->baseUrl, '/').'/chat';

        try {
            Log::info('AgentService: Sending request to Agent', [
                'url' => $url,
                'messages_count' => count($payload['messages'] ?? []),
            ]);

            $response = Http::timeout($this->timeout)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::error('AgentService: Request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Agent API request failed: '.$response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('AgentService: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if the Agent service is reachable
     */
    public function checkHealth(): bool
    {
        try {
            $url = rtrim($this->baseUrl, '/').'/health';
            $response = Http::timeout(5)->get($url);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get cache statistics from the Agent
     */
    public function getCacheStats(): array
    {
        try {
            $url = rtrim($this->baseUrl, '/').'/cache/stats';
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return [];
            }

            return $response->json('cache', []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
