@extends('platform.layout')

@section('title', $bookmark->exists ? 'Edit bookmark' : 'New bookmark')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">{{ $bookmark->exists ? 'Edit bookmark' : 'New bookmark' }}</h1>

                    <form method="POST"
                          @if ($bookmark->exists) action="{{ route('bookmarks.update', $bookmark) }}" @else action="{{ route('bookmarks.store') }}" @endif>
                        @csrf
                        @if ($bookmark->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="url" class="form-label">URL</label>
                            <input type="url" id="url" name="url" class="form-control @error('url') is-invalid @enderror"
                                   value="{{ old('url', $bookmark->url) }}" placeholder="https://..." required>
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Title and description are fetched automatically after saving.</div>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $bookmark->title) }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="collection" class="form-label">Collection</label>
                            <input type="text" id="collection" name="collection" class="form-control"
                                   value="{{ old('collection', $bookmark->collection) }}" list="collection-list">
                            <datalist id="collection-list">
                                @foreach ($collections as $collection)
                                    <option value="{{ $collection }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags <span class="text-muted small">(comma separated)</span></label>
                            <input type="text" id="tags" name="tags" class="form-control" value="{{ old('tags', $tags ?? '') }}">
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $bookmark->description) }}</textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('bookmarks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
