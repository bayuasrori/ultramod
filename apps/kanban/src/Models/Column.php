<?php

namespace PlatformApps\Kanban\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    protected $table = 'kanban_columns';
    public $timestamps = false;

    protected $fillable = ['kanban_board_id', 'name', 'position'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'kanban_board_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'kanban_column_id')->orderBy('position');
    }
}
