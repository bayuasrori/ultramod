<?php

namespace PlatformApps\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $table = 'form_builder_submissions';

    protected $fillable = ['form_id', 'submitted_by', 'answers'];

    protected $casts = [
        'answers' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The answer to one field. Fields added after this submission was made
     * simply have no answer.
     *
     * $blank is what an unanswered field looks like: a dash reads well in a
     * table, but an export wants a genuinely empty cell.
     */
    public function answerFor(Field $field, string $blank = '—'): string
    {
        $value = $this->answers[$field->key] ?? null;

        if ($value === null || $value === '') {
            return $blank;
        }

        if ($field->type === 'checkbox') {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
