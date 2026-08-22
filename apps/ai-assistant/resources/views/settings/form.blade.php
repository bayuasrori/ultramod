@extends('platform.layout')

@section('title', 'AI settings')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 card-title mb-0">AI provider settings</h1>
                        <a href="{{ route('ai-assistant.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                    </div>

                    <form method="POST" action="{{ route('ai-assistant.settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="provider" class="form-label">Provider</label>
                            <select id="provider" name="provider" class="form-select">
                                <option value="openai" @selected($values['provider'] === 'openai')>OpenAI-compatible API</option>
                                <option value="ollama" @selected($values['provider'] === 'ollama')>Ollama (local)</option>
                            </select>
                            <div class="form-text">
                                OpenAI-compatible works with OpenAI, Groq, OpenRouter, LM Studio and others.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" id="model" name="model" class="form-control @error('model') is-invalid @enderror"
                                   value="{{ old('model', $values['model']) }}" placeholder="gpt-4o-mini / llama3.1">
                            @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="base_url" class="form-label">Base URL <span class="text-muted small">(optional override)</span></label>
                            <input type="url" id="base_url" name="base_url" class="form-control @error('base_url') is-invalid @enderror"
                                   value="{{ old('base_url', $values['base_url']) }}" placeholder="https://api.openai.com/v1">
                            @error('base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="api_key" class="form-label">API key <span class="text-muted small">(not needed for Ollama)</span></label>
                            <input type="password" id="api_key" name="api_key" class="form-control @error('api_key') is-invalid @enderror"
                                   value="{{ old('api_key', $values['api_key']) }}" autocomplete="new-password">
                            @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
