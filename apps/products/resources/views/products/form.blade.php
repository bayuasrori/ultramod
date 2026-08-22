@extends('platform.layout')

@section('title', 'products form')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">@empty($record->id) Create @else Edit @endempty</h1>
                    <form method="POST" @empty($record->id) action="{{ route('products.store') }}" @else action="{{ route('products.update', $record) }}" @endempty>
                        @csrf
                        @unless($record->id)
                            @method('PUT')
                        @endunless
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $record->title ?? '') }}">
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="active" id="active" value="1" class="form-check-input" @checked(old('active', $record->active))>
                    <label class="form-check-label" for="active">Active</label>
                </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
