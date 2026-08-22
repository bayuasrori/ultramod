<?php

namespace PlatformApps\Notes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteRevision extends Model
{
    protected $fillable = ['note_id', 'title', 'content', 'created_by'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
