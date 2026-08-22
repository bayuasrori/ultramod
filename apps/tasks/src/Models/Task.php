<?php

namespace PlatformApps\Tasks\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = ['title', 'done'];

    protected $casts = [
        'done' => 'boolean',
    ];

    public function scopePending($query)
    {
        return $query->where('done', false);
    }

    public function scopeDone($query)
    {
        return $query->where('done', true);
    }
}
