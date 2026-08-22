<?php

namespace PlatformApps\NotesStatus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteStatusAssignment extends Model
{
    protected $fillable = ['note_id', 'note_status_id', 'assigned_by'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(NoteStatus::class, 'note_status_id');
    }
}
