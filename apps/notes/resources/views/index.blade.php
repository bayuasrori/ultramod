@extends('platform.layout')

@section('title', 'Notes')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Notes</h1>
        @can('notes.create')
            <a href="{{ route('notes.create') }}" class="btn btn-primary">+ New note</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('notes.index') }}" class="mb-3">
        <div class="input-group">
            <input type="text" name="q" class="form-control" value="{{ $q }}"
                   placeholder="Search title, content or #tag...">
            <button class="btn btn-outline-primary">Search</button>
            @if ($q)
                <a href="{{ route('notes.index') }}" class="btn btn-outline-secondary">Clear</a>
            @endif
        </div>
    </form>

    @if ($notes->isEmpty())
        <div class="alert alert-light text-center">
            No notes {{ $q ? 'matching your search' : 'yet' }}.
            @can('notes.create')<a href="{{ route('notes.create') }}">Create your first note</a>.@endcan
        </div>
    @else
        <div class="row">
            @foreach ($notes as $note)
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <a href="{{ route('notes.show', $note) }}" class="text-decoration-none">
                                <strong>{{ $note->title }}</strong>
                            </a>
                            @extensionslot('note.metadata', ['note' => $note])
                            <p class="small text-muted mb-2">{{ $note->excerpt() }}</p>
                            <div class="mt-auto">
                                @foreach ($note->tagStrings() as $tag)
                                    <span class="badge text-bg-light border">{{ $tag }}</span>
                                @endforeach
                                <span class="badge text-bg-secondary" title="revisions">{{ $note->revisions_count }} rev</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-end gap-1">
                            @can('notes.update')
                                <a href="{{ route('notes.edit', $note) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endcan
                            @can('notes.delete')
                                <form method="POST" action="{{ route('notes.destroy', $note) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this note?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $notes->links() }}</div>
    @endif
@endsection
