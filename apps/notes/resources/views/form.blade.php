@extends('platform.layout')

@section('title', $note->exists ? 'Edit note' : 'New note')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-3">{{ $note->exists ? 'Edit note' : 'New note' }}</h1>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ $note->exists ? route('notes.update', $note) : route('notes.store') }}">
                        @csrf
                        @if ($note->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $note->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content <span class="text-muted small">(Markdown supported)</span></label>
                            <textarea id="content" name="content" rows="12"
                                      class="form-control font-monospace @error('content') is-invalid @enderror">{{ old('content', $note->content) }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags <span class="text-muted small">(comma separated)</span></label>
                            <input type="text" id="tags" name="tags" class="form-control" value="{{ old('tags', $tags) }}"
                                   placeholder="draft, meeting-notes">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ $note->exists ? route('notes.show', $note) : route('notes.index') }}"
                               class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
