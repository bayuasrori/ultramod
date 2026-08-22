@extends('platform.layout')

@section('title', 'Kanban')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Kanban boards</h1>
    </div>

    <div class="row">
        <div class="col-md-7">
            @forelse ($boards as $board)
                <div class="card shadow-sm mb-3">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('kanban.boards.show', $board) }}" class="text-decoration-none">
                                <strong>{{ $board->name }}</strong>
                            </a>
                            <div class="text-muted small">{{ $board->columns_count }} columns</div>
                        </div>
                        <div class="d-flex gap-2">
                            @can('kanban.update')
                                <form method="POST" action="{{ route('kanban.boards.destroy', $board) }}"
                                      onsubmit="return confirm('Delete board {{ $board->name }} and all its tasks?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-light text-center">No boards yet — create your first one.</div>
            @endforelse
        </div>

        @can('kanban.create')
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 card-title">New board</h2>
                        <form method="POST" action="{{ route('kanban.boards.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                            </div>
                            <p class="text-muted small mb-3">Starts with Todo / Doing / Done columns.</p>
                            <button class="btn btn-primary">Create board</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
