@extends('platform.layout')

@section('title', $conversation->exists ? $conversation->title : 'New chat')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h4 mb-0">{{ $conversation->exists ? $conversation->title : 'New chat' }}</h1>
                @if ($conversation->exists)
                    <a href="{{ route('ai-assistant.index') }}" class="btn btn-outline-secondary btn-sm">All chats</a>
                @endif
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body chat-log" style="min-height: 16rem; max-height: 32rem; overflow-y: auto;">
                    @forelse ($messages as $message)
                        <div class="mb-3 {{ $message->role === 'user' ? 'text-end' : '' }}">
                            <div class="d-inline-block text-start p-2 rounded {{ $message->role === 'user' ? 'bg-primary-subtle' : 'bg-body-tertiary border' }}"
                                 style="max-width: 85%; white-space: pre-wrap;">{{ $message->content }}</div>
                            <div class="small text-muted">{{ $message->role }}</div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-5">Send a message to start the conversation.</div>
                    @endforelse
                </div>
            </div>

            @if ($conversation->exists)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST" action="{{ route('ai-assistant.send', $conversation) }}">
                            @csrf
                            <div class="mb-2">
                                <label for="content" class="form-label small text-muted">
                                    Model: <code>{{ $conversation->model ?? 'provider default' }}</code>
                                </label>
                                <textarea id="content" name="content" class="form-control @error('content') is-invalid @enderror"
                                          rows="3" placeholder="Type your message..." required autofocus>{{ old('content') }}</textarea>
                                @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted">Replies may take a few seconds.</span>
                                <button class="btn btn-primary">Send</button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST" action="{{ route('ai-assistant.store') }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="title" class="form-control" placeholder="Title (optional)"
                                           value="{{ old('title') }}">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="model" class="form-control" placeholder="Model (optional)"
                                           value="{{ old('model') }}">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100">Start</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
