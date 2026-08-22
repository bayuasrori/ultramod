<?php

namespace PlatformApps\Tasks\Http\Controllers;

use Illuminate\Routing\Controller;
use PlatformApps\Tasks\Http\Requests\StoreTaskRequest;
use PlatformApps\Tasks\Models\Task;

class TaskController extends Controller
{
    public function index()
    {
        return view('tasks::index', [
            'pending' => Task::pending()->orderByDesc('id')->get(),
            'done' => Task::done()->orderByDesc('id')->get(),
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        Task::create(['title' => $request->validated('title'), 'done' => false]);

        return redirect()->route('tasks.index');
    }

    public function toggle(Task $task)
    {
        $task->update(['done' => ! $task->done]);

        return redirect()->route('tasks.index');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return redirect()->route('tasks.index');
    }
}
