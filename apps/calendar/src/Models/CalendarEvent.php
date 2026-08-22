<?php

namespace PlatformApps\Calendar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = [
        'title', 'description', 'starts_at', 'ends_at', 'all_day',
        'location', 'attendees', 'reminder_minutes', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function attendeeList(): array
    {
        return array_filter(array_map('trim', explode(',', (string) $this->attendees)));
    }

    public function durationLabel(): string
    {
        if ($this->all_day) {
            return 'All day';
        }

        $label = $this->starts_at->format('H:i');
        if ($this->ends_at) {
            $label .= '–'.$this->ends_at->format('H:i');
        }

        return $label;
    }
}
