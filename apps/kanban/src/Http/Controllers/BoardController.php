<?php

namespace PlatformApps\Kanban\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Routing\Controller;
use PlatformApps\Kanban\Http\Requests\StoreBoardRequest;
use PlatformApps\Kanban\Models\Board;

class BoardController extends Controller
{
    public function index()
    {
        return view('kanban::boards.index', [
            'boards' => Board::withCount('columns')->latest('id')->get(),
        ]);
    }

    public function store(StoreBoardRequest $request, AuditLogger $audit)
    {
        $board = Board::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        foreach (['Todo', 'Doing', 'Done'] as $i => $name) {
            $board->columns()->create(['name' => $name, 'position' => $i]);
        }

        $audit->log('kanban.board.created', target: $board);

        return redirect()->route('kanban.boards.show', $board);
    }

    public function show(Board $board)
    {
        $board->load(['columns.tasks.assignee']);

        return view('kanban::boards.show', [
            'board' => $board,
            'users' => \App\Models\User::orderBy('name')->get(),
            'priorities' => \PlatformApps\Kanban\Models\Task::PRIORITIES,
        ]);
    }

    public function update(StoreBoardRequest $request, Board $board, AuditLogger $audit)
    {
        $board->update($request->validated());
        $audit->log('kanban.board.updated', target: $board);

        return redirect()->route('kanban.boards.show', $board);
    }

    public function destroy(Board $board, AuditLogger $audit)
    {
        $audit->log('kanban.board.deleted', metadata: ['name' => $board->name]);
        $board->delete();

        return redirect()->route('kanban.boards.index');
    }
}
