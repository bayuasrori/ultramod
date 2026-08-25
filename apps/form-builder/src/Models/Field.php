<?php

namespace PlatformApps\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Field extends Model
{
    /**
     * The field types the editor offers, mapped to their human label. Adding
     * a type here is all it takes for the builder, the renderer and the
     * validator to understand it.
     */
    public const TYPES = [
        'text' => 'Short text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'email' => 'Email',
        'date' => 'Date',
        'select' => 'Dropdown',
        'radio' => 'Single choice',
        'checkbox' => 'Checkbox',
    ];

    /**
     * Types whose answers come from a fixed list of choices.
     */
    public const CHOICE_TYPES = ['select', 'radio'];

    protected $table = 'form_builder_fields';

    protected $fillable = [
        'form_id', 'label', 'key', 'type', 'placeholder',
        'help', 'options', 'is_required', 'position',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'position' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    /**
     * @return array<int, string>
     */
    public function choices(): array
    {
        if (! $this->hasChoices()) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->options) ?: []),
            fn (string $choice) => $choice !== '',
        ));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * The validation rules this field contributes to its form.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable'];

        return array_merge($rules, match ($this->type) {
            'textarea' => ['string', 'max:5000'],
            'number' => ['numeric'],
            'email' => ['email', 'max:255'],
            'date' => ['date'],
            'checkbox' => ['boolean'],
            'select', 'radio' => ['string', 'in:'.implode(',', $this->choices())],
            default => ['string', 'max:255'],
        });
    }

    /**
     * A checkbox that is not ticked submits nothing at all, so "required"
     * has to mean "accepted" for it to be enforceable.
     */
    public function requiredCheckbox(): bool
    {
        return $this->type === 'checkbox' && $this->is_required;
    }
}
