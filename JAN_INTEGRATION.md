# Jan Integration Guide

This document describes the Jan API integration in MachinaAnalytica, enabling local LLM inference with comprehensive parameter control.

## Overview

Jan is a local AI assistant that runs LLMs on your machine. This integration provides full access to Jan's chat completion API with all available parameters for fine-tuned control over model behavior.

## Architecture

### Service Layer

- **`app/Services/Jan/JanService.php`** - Core service class handling all API communication with Jan
- Registered as a singleton in `AppServiceProvider`
- Uses Laravel's HTTP client for making requests

### Configuration

- **`config/services.php`** - Contains Jan configuration
- **`.env`** - Environment variables:
    - `JAN_URL` - Base URL of your Jan instance (default: `http://localhost:1337`)
    - `JAN_DEFAULT_MODEL` - Default model ID to use

**Example `.env` configuration:**

```env
JAN_URL='http://localhost:1337'
JAN_DEFAULT_MODEL='llama-3.2-1b'
```

### Controllers

- **`app/Http/Controllers/JanController.php`** - Handles Jan API operations

### Form Requests

- **`app/Http/Requests/SendJanPromptRequest.php`** - Validates chat completion requests with all parameters

## Features Implemented

### Model Management

- ✅ List all available models
- ✅ Automatic model selection from config
- ✅ Model persistence in environment

### Chat Completion

- ✅ Full OpenAI-compatible chat API
- ✅ All Jan-specific parameters supported
- ✅ Comprehensive validation
- ✅ Simple chat helper method for basic usage
- ✅ Advanced parameter control for fine-tuning

### Connection Management

- ✅ Connection health check
- ✅ Service availability detection
- ✅ Graceful error handling

## API Endpoints (`/jan/*`)

All endpoints require authentication:

**Core Operations:**

- `GET /jan/` - Jan chat interface page
- `GET /jan/check-connection` - Check Jan service availability

**Model Management:**

- `GET /jan/models` - List all available models

**Chat Operations:**

- `POST /jan/chat` - Send chat completion request with full parameter support

## Chat Completion Parameters

The Jan integration supports all parameters from the OpenAI-compatible API plus Jan-specific extensions:

### Required Parameters

- `model` - Model identifier (string)
- `messages` - Array of message objects with `role` and `content`

### Core Parameters

- `stream` - Enable streaming responses (boolean, default: false)
- `max_tokens` - Maximum tokens to generate (integer, default: 0 = unlimited)
- `temperature` - Sampling temperature 0-2 (float, default: 0.8)
- `top_k` - Top-K sampling (integer, default: 40)
- `top_p` - Nucleus sampling (float 0-1, default: 0.95)
- `stop` - Stop sequences (array of strings)

### Advanced Sampling Parameters

- `min_p` - Minimum probability threshold (float 0-1, default: 0.05)
- `typical_p` - Typical sampling (float 0-1, default: 1)
- `dynatemp_range` - Dynamic temperature range (float, default: 0)
- `dynatemp_exponent` - Dynamic temperature exponent (float, default: 1)
- `xtc_probability` - XTC sampling probability (float 0-1, default: 0)
- `xtc_threshold` - XTC threshold (float 0-1, default: 0.1)

### Repetition Control

- `presence_penalty` - Presence penalty -2 to 2 (float, default: 0)
- `frequency_penalty` - Frequency penalty -2 to 2 (float, default: 0)
- `repeat_penalty` - Repetition penalty (float, default: 1.1)
- `repeat_last_n` - Tokens to consider for repetition (integer, default: 64)

### DRY (Don't Repeat Yourself) Sampling

- `dry_multiplier` - DRY multiplier (float, default: 0)
- `dry_base` - DRY base (float, default: 1.75)
- `dry_allowed_length` - DRY allowed length (integer, default: 2)
- `dry_penalty_last_n` - DRY penalty last N tokens (integer, default: -1)
- `dry_sequence_breakers` - DRY sequence breakers (array, default: ["\n", ":", "\"", "*"])

### Mirostat Sampling

- `mirostat` - Mirostat mode 0/1/2 (integer, default: 0)
- `mirostat_tau` - Mirostat target entropy (float, default: 5)
- `mirostat_eta` - Mirostat learning rate (float, default: 0.1)

### Prediction Control

- `n_predict` - Number of tokens to predict (integer, default: -1 = unlimited)
- `n_indent` - Indentation level (integer, default: 0)
- `n_keep` - Keep N tokens from prompt (integer, default: 0)
- `t_max_predict_ms` - Max prediction time in ms (integer, default: 0 = unlimited)

### Output Control

- `grammar` - Grammar string for structured output (string)
- `json_schema` - JSON schema for structured output (object)
- `response_fields` - Fields to include in response (array)
- `return_tokens` - Return token information (boolean, default: false)
- `return_progress` - Return progress information (boolean, default: false)
- `timings_per_token` - Return timing per token (boolean, default: false)
- `post_sampling_probs` - Return post-sampling probabilities (boolean, default: false)

### Advanced Options

- `seed` - Random seed (integer, default: -1 = random)
- `ignore_eos` - Ignore end-of-sequence token (boolean, default: false)
- `logit_bias` - Logit bias object (object, default: {})
- `n_probs` - Return top N probabilities (integer, default: 0)
- `min_keep` - Minimum tokens to keep (integer, default: 0)
- `id_slot` - Slot ID (integer, default: -1)
- `cache_prompt` - Cache the prompt (boolean, default: true)
- `samplers` - Sampler order (array, default: ["dry", "top_k", "typ_p", "top_p", "min_p", "xtc", "temperature"])

### Extensions

- `lora` - LoRA adapters (array of objects with `id` and `scale`)
- `multimodal_data` - Multimodal data (array)

## Usage Examples

### Basic Chat

```php
use App\Services\Jan\JanService;

$janService = app(JanService::class);

$response = $janService->chat(
    message: 'Explain quantum computing',
    systemPrompt: 'You are a helpful physics teacher'
);

$result = $response->json();
```

### Advanced Chat with Custom Parameters

```php
$response = $janService->chatCompletion([
    'model' => 'llama-3.2-1b',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a code reviewer'],
        ['role' => 'user', 'content' => 'Review this code: <?php echo "Hello"; ?>'],
    ],
    'temperature' => 0.3,
    'max_tokens' => 1000,
    'top_p' => 0.9,
    'frequency_penalty' => 0.5,
    'presence_penalty' => 0.5,
]);
```

### List Available Models

```php
$response = $janService->listModels();
$models = $response->json('data');
```

### Check Connection

```php
if ($janService->checkConnection()) {
    // Jan is available
} else {
    // Jan is not available
}
```

## Testing

### Manual Testing

1. Start Jan application on your machine
2. Ensure Jan is listening on `http://localhost:1337` (or your configured URL)
3. Download and load a model in Jan
4. Set `JAN_DEFAULT_MODEL` in `.env` to match your model ID
5. Visit `/jan` to access the chat interface
6. Test the connection with `GET /jan/check-connection`
7. List models with `GET /jan/models`
8. Send chat requests to `POST /jan/chat`

### Postman Collection

A Postman collection (`jan_postman_collection.json`) is included with:

- Get Models endpoint with automatic model ID extraction
- Send Prompt endpoint with all parameters pre-configured
- Collection variables for base URL and model

## Error Handling

The integration includes comprehensive error handling:

- Service unavailability detection (503 status)
- Validation errors with detailed messages
- Connection timeout handling (120s timeout)
- Graceful degradation when Jan is not running

## Frontend Integration

To add a Jan chat interface, create a React component in `resources/js/Pages/Jan/Index.tsx` that:

1. Fetches available models on mount
2. Displays model selector
3. Implements chat interface
4. Handles all chat completion parameters
5. Shows connection status

## Comparison with AnythingLLM

| Feature              | Jan       | AnythingLLM     |
| -------------------- | --------- | --------------- |
| Authentication       | None      | Bearer Token    |
| Model Management     | Built-in  | Workspace-based |
| Document Integration | Limited   | Full RAG        |
| Parameter Control    | Extensive | Limited         |
| Workspace Support    | No        | Yes             |
| Thread Support       | No        | Yes             |
| Local Inference      | Yes       | Optional        |
| API Compatibility    | OpenAI    | Custom          |

## Performance Considerations

- Jan runs models locally, so performance depends on your hardware
- Larger models require more RAM and may be slower
- Set appropriate `max_tokens` to control response length
- Use `cache_prompt=true` for faster repeated queries
- Consider `t_max_predict_ms` for timeout control
- Streaming is available via `stream=true` for long responses

## Next Steps

1. Create frontend UI in `resources/js/Pages/Jan/Index.tsx`
2. Add model selection interface
3. Implement streaming support in frontend
4. Add conversation history management
5. Create tests in `tests/Feature/JanTest.php`
6. Add system prompt templates
7. Implement preset parameter configurations

## Resources

- [Jan Official Documentation](https://jan.ai/docs)
- [Jan GitHub Repository](https://github.com/janhq/jan)
- [OpenAI API Compatibility](https://platform.openai.com/docs/api-reference/chat)
