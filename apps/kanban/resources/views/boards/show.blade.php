@extends('platform.layout')

@section('title', $board->name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">{{ $board->name }}</h1>
            @if ($board->description)
                <span class="text-muted small">{{ $board->description }}</span>
            @endif
        </div>
        <a href="{{ route('kanban.boards.index') }}" class="btn btn-outline-secondary btn-sm">All boards</a>
    </div>

    <div class="row g-3 kanban-board" data-board="{{ $board->id }}">
        @foreach ($board->columns as $column)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card bg-body-tertiary border">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                        <strong class="small text-uppercase">{{ $column->name }}</strong>
                        <span class="badge text-bg-light">{{ $column->tasks->count() }}</span>
                    </div>
                    <div class="card-body kanban-column py-2" style="min-height: 8rem;"
                         data-column="{{ $column->id }}">
                        @forelse ($column->tasks as $task)
                            <div class="card mb-2 shadow-sm kanban-task" draggable="true" data-task="{{ $task->id }}">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <a href="{{ route('kanban.tasks.edit', $task) }}" class="text-decoration-none small fw-semibold">
                                            {{ $task->title }}
                                        </a>
                                        <span class="badge {{ $task->priorityBadge() }}">{{ $task->priority }}</span>
                                    </div>
                                    @if ($task->due_date)
                                        <div class="small text-muted">
                                            Due {{ $task->due_date->format('M d') }}
                                            @if ($task->due_date->isPast())
                                                <span class="text-danger fw-semibold">overdue</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($task->assignee)
                                        <div class="small text-muted">Assigned to {{ $task->assignee->name }}</div>
                                    @endif
                                    @foreach ($task->tagStrings() as $tag)
                                        <span class="badge text-bg-info mb-1">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-muted small text-center py-3 kanban-empty">No tasks</div>
                        @endforelse
                    </div>
                    @can('kanban.create')
                        <div class="card-footer bg-transparent py-2">
                            <form method="POST" action="{{ route('kanban.tasks.store', $column) }}" class="input-group input-group-sm">
                                @csrf
                                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       placeholder="New task..." required>
                                <button class="btn btn-outline-primary" type="submit">Add</button>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>
        @endforeach

        @can('kanban.update')
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card border-dashed h-100">
                    <div class="card-body">
                        <form method="POST" action="{{ route('kanban.columns.store', $board) }}">
                            @csrf
                            <label class="form-label small fw-semibold text-uppercase text-muted">Add column</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="name" class="form-control" placeholder="Column name" required>
                                <button class="btn btn-outline-primary">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    @can('kanban.update')
        <script>
            (function () {
                var csrf = document.querySelector('meta[name="csrf-token"]');

                document.querySelectorAll('.kanban-task').forEach(function (el) {
                    el.addEventListener('dragstart', function (e) {
                        e.dataTransfer.setData('text/plain', el.dataset.task);
                        el.classList.add('opacity-50');
                    });
                    el.addEventListener('dragend', function () {
                        el.classList.remove('opacity-50');
                    });
                });

                document.querySelectorAll('.kanban-column').forEach(function (col) {
                    col.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        col.classList.add('bg-light');
                    });
                    col.addEventListener('dragleave', function () {
                        col.classList.remove('bg-light');
                    });
                    col.addEventListener('drop', function (e) {
                        e.preventDefault();
                        col.classList.remove('bg-light');

                        var taskId = e.dataTransfer.getData('text/plain');
                        if (!taskId) return;

                        fetch('/kanban/tasks/' + taskId + '/move', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                kanban_column_id: parseInt(col.dataset.column, 10),
                            }),
                        }).then(function () { window.location.reload(); });
                    });
                });
            })();
        </script>
    @endcan
@endpush
