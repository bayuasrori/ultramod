@extends('platform.layout')

@section('title', 'Note Statuses')

@section('content')
    <h1 class="h3 mb-3">Note Statuses</h1>

    <div class="row">
        @foreach ($statuses as $status)
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <span class="badge text-bg-{{ $status->color }} mb-2">{{ $status->name }}</span>
                        <div class="h4 mb-0">{{ $status->assignments_count }}</div>
                        <div class="small text-muted">notes</div>
                        <a href="{{ route('notes-status.notes', $status) }}" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
