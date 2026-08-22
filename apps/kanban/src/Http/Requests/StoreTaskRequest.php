<?php

namespace PlatformApps\Kanban\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'priority' => ['nullable', 'in:' . implode(',', \PlatformApps\Kanban\Models\Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // tags arrive as a comma-separated string from the form
        if (is_string($this->input('tags'))) {
            $this->merge([
                'tags' => array_filter(explode(',', $this->input('tags'))),
            ]);
        }
    }
}
