@extends('tasks::layout')

@section('title', 'Tasks')

@section('content')
    <h1 class="h3 mb-3">Tasks</h1>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('tasks.store') }}" class="row g-2">
                @csrf
                <div class="col-auto flex-grow-1">
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           placeholder="What needs to be done?" value="{{ old('title') }}">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pending</span>
                    <span class="badge text-bg-warning">{{ $pending->count() }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($pending as $task)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <form method="POST" action="{{ route('tasks.toggle', $task) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as done">✔</button>
                            </form>
                            <span class="flex-grow-1">{{ $task->title }}</span>
                            <form method="POST" action="{{ route('tasks.destroy', $task) }}"
                                  onsubmit="return confirm('Delete this task?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">✕</button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item text-muted text-center">No pending tasks.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Done</span>
                    <span class="badge text-bg-success">{{ $done->count() }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($done as $task)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <form method="POST" action="{{ route('tasks.toggle', $task) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as pending">↺</button>
                            </form>
                            <span class="flex-grow-1 text-decoration-line-through text-muted">{{ $task->title }}</span>
                            <form method="POST" action="{{ route('tasks.destroy', $task) }}"
                                  onsubmit="return confirm('Delete this task?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">✕</button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item text-muted text-center">Nothing done yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
