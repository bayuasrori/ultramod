@extends('platform.layout')

@section('title', 'Edit task')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">Edit task</h1>

                    <form method="POST" action="{{ route('kanban.tasks.update', $task) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $task->title) }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4">{{ old('description', $task->description) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="kanban_column_id" class="form-label">Column</label>
                                <select id="kanban_column_id" name="kanban_column_id" class="form-select">
                                    @foreach ($columns as $column)
                                        <option value="{{ $column->id }}"
                                                @selected(old('kanban_column_id', $task->kanban_column_id) == $column->id)>
                                            {{ $column->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select id="priority" name="priority" class="form-select">
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>
                                            {{ $priority }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="due_date" class="form-label">Due date</label>
                                <input type="date" id="due_date" name="due_date" class="form-control"
                                       value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="assignee_id" class="form-label">Assignee</label>
                                <select id="assignee_id" name="assignee_id" class="form-select">
                                    <option value="">— unassigned —</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                                @selected(old('assignee_id', $task->assignee_id) == $user->id)>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="tags" class="form-label">Tags <span class="text-muted small">(comma separated)</span></label>
                            <input type="text" id="tags" name="tags" class="form-control" value="{{ old('tags', $tags) }}">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('kanban.boards.show', $task->column->kanban_board_id) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
