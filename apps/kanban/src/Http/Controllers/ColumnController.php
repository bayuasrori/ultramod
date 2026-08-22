<?php

namespace PlatformApps\Kanban\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Routing\Controller;
use PlatformApps\Kanban\Http\Requests\StoreColumnRequest;
use PlatformApps\Kanban\Models\Board;
use PlatformApps\Kanban\Models\Column;

class ColumnController extends Controller
{
    public function store(StoreColumnRequest $request, Board $board, AuditLogger $audit)
    {
        $position = (int) $board->columns()->max('position') + 1;

        $column = $board->columns()->create([
            'name' => $request->validated('name'),
            'position' => $position,
        ]);

        $audit->log('kanban.column.created', target: $column);

        return redirect()->route('kanban.boards.show', $board);
    }

    public function update(StoreColumnRequest $request, Board $board, Column $column, AuditLogger $audit)
    {
        $column->update(['name' => $request->validated('name')]);
        $audit->log('kanban.column.updated', target: $column);

        return redirect()->route('kanban.boards.show', $board);
    }

    public function destroy(Board $board, Column $column, AuditLogger $audit)
    {
        $audit->log('kanban.column.deleted', metadata: ['name' => $column->name, 'board' => $board->name]);
        $column->delete();

        return redirect()->route('kanban.boards.show', $board);
    }
}
