<?php

namespace PlatformApps\Calendar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'string', 'max:1000'],
            'reminder_minutes' => ['nullable', 'integer', 'in:15,30,60,1440'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('all_day')) {
            $this->merge(['ends_at' => $this->input('starts_at')]);
        }
    }
}
