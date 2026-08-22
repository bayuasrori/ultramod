@extends('platform.layout')

@section('title', 'Notes: '.$status->name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">
            <span class="badge text-bg-{{ $status->color }}">{{ $status->name }}</span> notes
        </h1>
        <a href="{{ route('notes-status.index') }}" class="btn btn-outline-secondary btn-sm">All statuses</a>
    </div>

    @forelse ($notes as $note)
        <div class="card shadow-sm mb-2">
            <div class="card-body py-2">
                <a href="{{ route('notes.show', $note) }}" class="text-decoration-none fw-semibold">{{ $note->title }}</a>
                <div class="small text-muted">{{ $note->excerpt(100) }}</div>
            </div>
        </div>
    @empty
        <div class="alert alert-light text-center">No notes with this status.</div>
    @endforelse
@endsection
