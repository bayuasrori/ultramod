<?php

namespace PlatformApps\Notes\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PlatformApps\Notes\Models\Note;

class NoteCreated
{
    use Dispatchable;

    public function __construct(public Note $note)
    {
    }
}
