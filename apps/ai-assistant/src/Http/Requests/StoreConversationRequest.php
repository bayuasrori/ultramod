<?php

namespace PlatformApps\AiAssistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
