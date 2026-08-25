<?php

namespace PlatformApps\FormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'success_message' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
