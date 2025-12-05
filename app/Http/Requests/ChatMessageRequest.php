<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:10000'],
            'workspace_slug' => ['required', 'string', 'max:255'],
            'mode' => ['nullable', 'string', 'in:chat,query'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message to send.',
            'message.max' => 'Your message is too long. Please keep it under 10,000 characters.',
            'workspace_slug.required' => 'A workspace is required to send a message.',
            'mode.in' => 'The mode must be either "chat" or "query".',
        ];
    }
}
