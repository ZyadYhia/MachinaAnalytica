<?php

namespace App\Services\Jan;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class JanService
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $authToken = null
    ) {}

    /**
     * Get configured HTTP client with base URL.
     */
    protected function client(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->timeout(600); // Jan can take much longer for complex model responses (10 minutes)
    }

    /**
     * Get list of available models.
     */
    public function listModels(): Response
    {
        return $this->client()->get('models');
    }

    /**
     * Send a chat completion request with full parameter support.
     *
     * @param  array  $data  The complete chat completion request data
     */
    public function chatCompletion(array $data): Response
    {
        // Set defaults for required fields if not provided
        $payload = array_merge([
            'model' => config('services.jan.default_model'),
            'messages' => [],
            // 'stream' => false,
            // 'max_tokens' => 0,
            // 'stop' => [],
            // 'temperature' => 0.8,
            // 'dynatemp_range' => 0,
            // 'dynatemp_exponent' => 1,
            // 'top_k' => 40,
            // 'top_p' => 0.95,
            // 'min_p' => 0.05,
            // 'typical_p' => 1,
            // 'n_predict' => -1,
            // 'n_indent' => 0,
            // 'n_keep' => 0,
            // 'presence_penalty' => 0,
            // 'frequency_penalty' => 0,
            // 'repeat_penalty' => 1.1,
            // 'repeat_last_n' => 64,
            // 'dry_multiplier' => 0,
            // 'dry_base' => 1.75,
            // 'dry_allowed_length' => 2,
            // 'dry_penalty_last_n' => -1,
            // 'dry_sequence_breakers' => ["\n", ':', '"', '*'],
            // 'xtc_probability' => 0,
            // 'xtc_threshold' => 0.1,
            // 'mirostat' => 0,
            // 'mirostat_tau' => 5,
            // 'mirostat_eta' => 0.1,
            // 'grammar' => 'string',
            // 'json_schema' => (object) [],
            // 'seed' => -1,
            // 'ignore_eos' => false,
            // 'logit_bias' => (object) [],
            // 'n_probs' => 0,
            // 'min_keep' => 0,
            // 't_max_predict_ms' => 0,
            // 'id_slot' => -1,
            'cache_prompt' => true,
            // 'return_tokens' => false,
            // 'samplers' => [
            //     'dry',
            //     'top_k',
            //     'typ_p',
            //     'top_p',
            //     'min_p',
            //     'xtc',
            //     'temperature',
            // ],
            // 'timings_per_token' => false,
            // 'return_progress' => false,
            // 'post_sampling_probs' => false,
            // 'response_fields' => [],
            // 'lora' => [],
            // 'multimodal_data' => [],
        ], $data);

        return $this->client()
            ->timeout(600) // Extended timeout for chat completions (10 minutes for VL models)
            ->post('chat/completions', $payload);
    }

    /**
     * Simple chat method for basic usage.
     *
     * @param  string  $message  The user message
     * @param  string|null  $model  Optional model override
     * @param  string|null  $systemPrompt  Optional system prompt
     * @param  array  $options  Additional options to override defaults
     */
    public function chat(
        string $message,
        ?string $model = null,
        ?string $systemPrompt = null,
        array $options = []
    ): Response {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $data = array_merge([
            'model' => $model ?? config('services.jan.default_model'),
            'messages' => $messages,
        ], $options);

        return $this->chatCompletion($data);
    }

    /**
     * Chat with conversation history to continue a conversation.
     *
     * @param  array  $conversationHistory  Array of message objects with 'role' and 'content'
     * @param  string  $newMessage  The new user message to add
     * @param  string|null  $model  Optional model override
     * @param  int|null  $idSlot  Optional id_slot from previous response for conversation continuity
     * @param  array  $options  Additional options to override defaults
     */
    public function chatWithHistory(
        array $conversationHistory,
        string $newMessage,
        ?string $model = null,
        ?int $idSlot = null,
        array $options = []
    ): Response {
        // Add the new message to the conversation history
        $messages = array_merge($conversationHistory, [
            [
                'role' => 'user',
                'content' => $newMessage,
            ],
        ]);

        $data = array_merge([
            'model' => $model ?? config('services.jan.default_model'),
            'messages' => $messages,
        ], $options);

        // Add id_slot if provided for conversation continuity
        if ($idSlot !== null) {
            $data['id_slot'] = $idSlot;
            $data['cache_prompt'] = true; // Enable prompt caching with slot
        }

        return $this->chatCompletion($data);
    }

    /**
     * Extract assistant message from chat completion response.
     *
     * @param  Response  $response  The chat completion response
     * @return string|null The assistant's message content or null if not found
     */
    public function extractMessageFromResponse(Response $response): ?string
    {
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Build a message array for conversation history.
     *
     * @param  string  $role  The role (user, assistant, or system)
     * @param  string  $content  The message content
     */
    public function buildMessage(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => $content,
        ];
    }

    /**
     * Extract id_slot from response for conversation continuity.
     * The id_slot can be used in the next request to continue the same conversation
     * with cached context, improving performance and maintaining state.
     *
     * @param  Response  $response  The chat completion response
     * @return int|null The id_slot value or null if not found
     */
    public function extractIdSlot(Response $response): ?int
    {
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        // Jan API may return id_slot in various places
        return $data['id_slot'] ?? $data['slot_id'] ?? null;
    }

    /**
     * Check if Jan service is available.
     */
    public function checkConnection(): bool
    {
        try {
            $response = $this->listModels();

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
