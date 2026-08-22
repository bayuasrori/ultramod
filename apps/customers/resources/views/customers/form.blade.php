@extends('platform.layout')

@section('title', 'customers form')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">@empty($record->id) Create @else Edit @endempty</h1>
                    <form method="POST" @empty($record->id) action="{{ route('customers.store') }}" @else action="{{ route('customers.update', $record) }}" @endempty>
                        @csrf
                        @unless($record->id)
                            @method('PUT')
                        @endunless
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $record->full_name ?? '') }}">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="text" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $record->email ?? '') }}">
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $record->notes ?? '') }}</textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="vip" id="vip" value="1" class="form-check-input" @checked(old('vip', $record->vip))>
                    <label class="form-check-label" for="vip">Vip</label>
                </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
