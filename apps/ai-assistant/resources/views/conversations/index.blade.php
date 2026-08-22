@extends('platform.layout')

@section('title', 'AI Assistant')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Conversations</h1>
        <div class="d-flex gap-2">
            @can('ai.manage-settings')
                <a href="{{ route('ai-assistant.settings') }}" class="btn btn-outline-secondary btn-sm">Settings</a>
            @endcan
            @can('ai.chat')
                <a href="{{ route('ai-assistant.create') }}" class="btn btn-primary btn-sm">+ New chat</a>
            @endcan
        </div>
    </div>

    @forelse ($conversations as $conversation)
        <div class="card shadow-sm mb-2">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <div>
                    <a href="{{ route('ai-assistant.show', $conversation) }}" class="text-decoration-none fw-semibold">
                        {{ $conversation->title }}
                    </a>
                    <div class="small text-muted">
                        {{ $conversation->model ?? 'default model' }} ·
                        {{ optional($conversation->lastMessage->first())->content ? \Illuminate\Support\Str::limit($conversation->lastMessage->first()->content, 80) : 'no messages yet' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('ai-assistant.destroy', $conversation) }}"
                      onsubmit="return confirm('Delete this conversation?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light text-center">No conversations yet — start a new chat.</div>
    @endforelse

    <div class="mt-3">{{ $conversations->links() }}</div>
@endsection
