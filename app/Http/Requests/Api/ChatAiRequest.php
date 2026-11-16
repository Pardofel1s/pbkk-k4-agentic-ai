<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ChatAiRequest extends FormRequest
{
    public const MAX_PROMPT_LENGTH = 1000;

    /**
     * Access is decided by the auth:sanctum middleware on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Prompt wajib diisi.',
            'prompt.string' => 'Prompt harus berupa teks.',
            'prompt.max' => 'Prompt maksimal :max karakter.',
        ];
    }
}
