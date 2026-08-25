@extends('platform.layout')

@section('title', $form->exists ? 'Edit form' : 'New form')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">{{ $form->exists ? 'Form settings' : 'New form' }}</h1>

                    <form method="POST" action="{{ $form->exists ? route('form-builder.update', $form) : route('form-builder.store') }}">
                        @csrf
                        @if ($form->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" required autofocus
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $form->title) }}">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">The public link is derived from the title.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $form->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="success_message" class="form-label">Message after submitting</label>
                            <input type="text" id="success_message" name="success_message"
                                   class="form-control @error('success_message') is-invalid @enderror"
                                   placeholder="Thanks — your response was recorded."
                                   value="{{ old('success_message', $form->success_message) }}">
                            @error('success_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="hidden" name="is_published" value="0">
                            <input type="checkbox" id="is_published" name="is_published" value="1" class="form-check-input"
                                   @checked(old('is_published', $form->is_published))>
                            <label class="form-check-label" for="is_published">
                                Published — anyone who may submit can open the link
                            </label>
                        </div>

                        <div class="form-check mb-4">
                            <input type="hidden" name="is_public" value="0">
                            <input type="checkbox" id="is_public" name="is_public" value="1" class="form-check-input"
                                   @checked(old('is_public', $form->is_public))>
                            <label class="form-check-label" for="is_public">
                                Public — anyone can fill this form without logging in
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('form-builder.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
