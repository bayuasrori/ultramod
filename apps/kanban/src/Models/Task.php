<?php

namespace PlatformApps\Kanban\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Task extends Model
{
    protected $table = 'kanban_tasks';
    protected $fillable = [
        'kanban_column_id', 'title', 'description', 'priority', 'due_date', 'assignee_id', 'position',
    ];

    protected $casts = ['due_date' => 'date'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class, 'kanban_column_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assignee_id');
    }

    public function tagStrings(): array
    {
        return \DB::table('kanban_task_tag')->where('kanban_task_id', $this->id)->pluck('tag')->all();
    }

    public function syncTags(array $tags): void
    {
        \DB::table('kanban_task_tag')->where('kanban_task_id', $this->id)->delete();

        $unique = array_unique(array_filter(array_map('trim', $tags)));
        foreach ($unique as $tag) {
            \DB::table('kanban_task_tag')->insert([
                'kanban_task_id' => $this->id,
                'tag' => $tag,
            ]);
        }
    }

    public function priorityBadge(): string
    {
        return match ($this->priority) {
            'urgent' => 'text-bg-danger',
            'high' => 'text-bg-warning',
            'low' => 'text-bg-light',
            default => 'text-bg-secondary',
        };
    }
}
