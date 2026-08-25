<?php

namespace PlatformApps\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $table = 'form_builder_forms';

    protected $fillable = [
        'title', 'slug', 'description', 'success_message', 'is_published', 'is_public', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class, 'form_id')->orderBy('position')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'form_id')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * A slug that is unique across forms, derived from the title. The optional
     * $ignoreId keeps a form from colliding with itself while being renamed.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * A key that is unique within this form, derived from a field label.
     */
    public function uniqueFieldKey(string $label, ?int $ignoreId = null): string
    {
        $base = Str::snake(Str::slug($label, '_')) ?: 'field';
        $key = $base;
        $suffix = 2;

        while ($this->fields()->where('key', $key)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    /**
     * Validation rules assembled from the form's own field definitions —
     * this is what makes a user-built form enforce its own contract.
     *
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules[$field->key] = $field->rules();
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return $this->fields->pluck('label', 'key')->all();
    }

    public function nextFieldPosition(): int
    {
        return (int) $this->fields()->max('position') + 1;
    }
}
