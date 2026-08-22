<?php

namespace PlatformApps\Kanban\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    protected $table = 'kanban_boards';
    protected $fillable = ['name', 'description', 'created_by'];

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class, 'kanban_board_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
