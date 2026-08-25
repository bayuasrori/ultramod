<?php

namespace PlatformApps\FormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use PlatformApps\FormBuilder\Models\Field;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(Field::TYPES))],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A dropdown or single-choice field without choices would render an empty
     * control that can never be filled in, so it is rejected up front.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! in_array($this->input('type'), Field::CHOICE_TYPES, true)) {
                    return;
                }

                $choices = array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', (string) $this->input('options')) ?: [],
                ));

                if (count($choices) < 2) {
                    $validator->errors()->add('options', 'A choice field needs at least two options, one per line.');
                }
            },
        ];
    }
}
