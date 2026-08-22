@extends('platform.layout')

@section('title', $note->title)

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h1 class="h3 mb-0">{{ $note->title }}</h1>
                <div class="d-flex gap-1">
                    @can('notes.update')
                        <a href="{{ route('notes.edit', $note) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @endcan
                    @can('notes.delete')
                        <form method="POST" action="{{ route('notes.destroy', $note) }}"
                              onsubmit="return confirm('Delete this note?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    @endcan
                </div>
            </div>

            @extensionslot('note.header', ['note' => $note])

            <div class="mb-2">
                @foreach ($note->tagStrings() as $tag)
                    <span class="badge text-bg-light border">{{ $tag }}</span>
                @endforeach
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body note-content">
                    {!! $note->html() !!}
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Attachments</div>
                <div class="card-body">
                    @forelse ($attachments as $attachment)
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <div>
                                <a href="{{ route('notes.attachments.download', [$note, $attachment->id]) }}">{{ $attachment->name }}</a>
                                <span class="text-muted small">
                                    {{ number_format($attachment->size / 1024, 1) }} KB · {{ $attachment->mime_type }}
                                </span>
                            </div>
                            @can('notes.update')
                                <form method="POST" action="{{ route('notes.attachments.delete', [$note, $attachment->id]) }}"
                                      onsubmit="return confirm('Delete attachment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">&times;</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <span class="text-muted small">No attachments.</span>
                    @endforelse

                    @can('notes.update')
                        <form method="POST" action="{{ route('notes.attachments.upload', $note) }}"
                              enctype="multipart/form-data" class="d-flex gap-2 mt-3">
                            @csrf
                            <input type="file" name="file" class="form-control form-control-sm" required>
                            <button class="btn btn-sm btn-outline-primary">Upload</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @extensionslot('note.sidebar', ['note' => $note])

            <div class="card shadow-sm">
                <div class="card-header bg-transparent">Revision history</div>
                <div class="card-body">
                    @forelse ($revisions as $revision)
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <div class="small">
                                {{ $revision->created_at->format('M d, H:i') }}
                                <span class="text-muted">by {{ $revision->author?->name ?? 'system' }}</span>
                            </div>
                            @can('notes.update')
                                <form method="POST" action="{{ route('notes.revisions.restore', [$note, $revision]) }}">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn btn-sm btn-outline-secondary">Restore</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <span class="text-muted small">No revisions yet — edits create snapshots automatically.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
