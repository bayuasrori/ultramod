<?php

namespace PlatformApps\Kanban\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PlatformApps\Kanban\Http\Requests\StoreTaskRequest;
use PlatformApps\Kanban\Models\Column;
use PlatformApps\Kanban\Models\Task;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Column $column, AuditLogger $audit)
    {
        $task = $column->tasks()->create([
            ...$request->validated(),
            'position' => (int) $column->tasks()->max('position') + 1,
        ]);

        $task->syncTags($request->input('tags', []));
        $audit->log('kanban.task.created', target: $task);

        return redirect()->route('kanban.boards.show', $column->kanban_board_id);
    }

    public function edit(Task $task)
    {
        return view('kanban::tasks.form', [
            'task' => $task->load('column.board'),
            'columns' => $task->column->board->columns,
            'users' => \App\Models\User::orderBy('name')->get(),
            'priorities' => Task::PRIORITIES,
            'tags' => implode(', ', $task->tagStrings()),
        ]);
    }

    public function update(StoreTaskRequest $request, Task $task, AuditLogger $audit)
    {
        $task->update($request->validated());
        $task->syncTags($request->input('tags', []));
        $audit->log('kanban.task.updated', target: $task);

        return redirect()->route('kanban.boards.show', $task->column->kanban_board_id);
    }

    public function destroy(Task $task, AuditLogger $audit)
    {
        $boardId = $task->column->kanban_board_id;

        $audit->log('kanban.task.deleted', metadata: ['title' => $task->title]);
        $task->delete();

        return redirect()->route('kanban.boards.show', $boardId);
    }

    public function move(Request $request, Task $task, AuditLogger $audit)
    {
        $validated = $request->validate([
            'kanban_column_id' => ['required', 'integer', 'exists:kanban_columns,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $task->update([
            'kanban_column_id' => $validated['kanban_column_id'],
            'position' => $validated['position'] ?? $task->position,
        ]);

        $audit->log('kanban.task.moved', target: $task, metadata: [
            'column' => (int) $validated['kanban_column_id'],
        ]);

        return response()->json(['ok' => true]);
    }
}
