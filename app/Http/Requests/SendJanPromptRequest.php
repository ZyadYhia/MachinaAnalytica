<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendJanPromptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'model' => ['sometimes', 'string'],
            'messages' => ['sometimes', 'required_without:message', 'array', 'min:1'],
            'messages.*.role' => ['required_with:messages', 'string', 'in:system,user,assistant'],
            'messages.*.content' => ['required_with:messages', 'string'],

            // For chatWithHistory endpoint
            'message' => ['sometimes', 'required_without:messages', 'string'],
            'conversation_id' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'string'],

            'stream' => ['boolean'],
            'max_tokens' => ['integer', 'min:0'],
            'stop' => ['array'],
            'temperature' => ['numeric', 'min:0', 'max:2'],
            'dynatemp_range' => ['numeric', 'min:0'],
            'dynatemp_exponent' => ['numeric', 'min:0'],
            'top_k' => ['integer', 'min:0'],
            'top_p' => ['numeric', 'min:0', 'max:1'],
            'min_p' => ['numeric', 'min:0', 'max:1'],
            'typical_p' => ['numeric', 'min:0', 'max:1'],
            'n_predict' => ['integer'],
            'n_indent' => ['integer', 'min:0'],
            'n_keep' => ['integer', 'min:0'],
            'presence_penalty' => ['numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['numeric', 'min:-2', 'max:2'],
            'repeat_penalty' => ['numeric', 'min:0'],
            'repeat_last_n' => ['integer', 'min:0'],
            'dry_multiplier' => ['numeric', 'min:0'],
            'dry_base' => ['numeric', 'min:0'],
            'dry_allowed_length' => ['integer', 'min:0'],
            'dry_penalty_last_n' => ['integer'],
            'dry_sequence_breakers' => ['array'],
            'xtc_probability' => ['numeric', 'min:0', 'max:1'],
            'xtc_threshold' => ['numeric', 'min:0', 'max:1'],
            'mirostat' => ['integer', 'in:0,1,2'],
            'mirostat_tau' => ['numeric', 'min:0'],
            'mirostat_eta' => ['numeric', 'min:0'],
            'grammar' => ['string'],
            'json_schema' => ['array'],
            'seed' => ['integer'],
            'ignore_eos' => ['boolean'],
            'logit_bias' => ['array'],
            'n_probs' => ['integer', 'min:0'],
            'min_keep' => ['integer', 'min:0'],
            't_max_predict_ms' => ['integer', 'min:0'],
            'id_slot' => ['integer'],
            'cache_prompt' => ['boolean'],
            'return_tokens' => ['boolean'],
            'samplers' => ['array'],
            'timings_per_token' => ['boolean'],
            'return_progress' => ['boolean'],
            'post_sampling_probs' => ['boolean'],
            'response_fields' => ['array'],
            'lora' => ['array'],
            'lora.*.id' => ['integer'],
            'lora.*.scale' => ['numeric'],
            'multimodal_data' => ['array'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'model.required' => 'A model must be specified.',
            'messages.required' => 'Messages are required.',
            'messages.min' => 'At least one message is required.',
            'messages.*.role.in' => 'Message role must be system, user, or assistant.',
            'messages.*.content.required' => 'Message content cannot be empty.',
            'temperature.max' => 'Temperature must not exceed 2.',
            'stream.boolean' => 'Stream must be true or false.',
        ];
    }
}
