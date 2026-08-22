@extends('platform.layout')

@section('title', $event->exists ? 'Edit event' : 'New event')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">{{ $event->exists ? 'Edit event' : 'New event' }}</h1>

                    <form method="POST"
                          @if ($event->exists) action="{{ route('calendar.events.update', $event) }}" @else action="{{ route('calendar.events.store') }}" @endif>
                        @csrf
                        @if ($event->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $event->title) }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="starts_at" class="form-label">Starts</label>
                                <input type="datetime-local" id="starts_at" name="starts_at"
                                       class="form-control @error('starts_at') is-invalid @enderror"
                                       value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i') ?? $defaultDate.'T09:00') }}" required>
                                @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="ends_at" class="form-label">Ends</label>
                                <input type="datetime-local" id="ends_at" name="ends_at"
                                       class="form-control @error('ends_at') is-invalid @enderror"
                                       value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}">
                                @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="hidden" name="all_day" value="0">
                            <input type="checkbox" id="all_day" name="all_day" value="1" class="form-check-input"
                                   @checked(old('all_day', $event->all_day))>
                            <label class="form-check-label" for="all_day">All day</label>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-control @error('location') is-invalid @enderror"
                                   value="{{ old('location', $event->location) }}">
                            @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="attendees" class="form-label">Attendees <span class="text-muted small">(comma-separated emails)</span></label>
                            <input type="text" id="attendees" name="attendees" class="form-control"
                                   value="{{ old('attendees', $event->attendees) }}" placeholder="a@corp.com, b@corp.com">
                        </div>

                        <div class="mb-3">
                            <label for="reminder_minutes" class="form-label">Reminder</label>
                            <select id="reminder_minutes" name="reminder_minutes" class="form-select">
                                <option value="">— none —</option>
                                @foreach (['15' => '15 minutes before', '30' => '30 minutes before', '60' => '1 hour before', '1440' => '1 day before'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reminder_minutes', $event->reminder_minutes) == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $event->description) }}</textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
