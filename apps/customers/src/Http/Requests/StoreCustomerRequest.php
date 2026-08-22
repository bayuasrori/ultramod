<?php

namespace PlatformApps\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:255'],
            'vip' => ['nullable', 'boolean'],
        ];
    }
}
