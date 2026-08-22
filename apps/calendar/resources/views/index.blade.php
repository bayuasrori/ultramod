@extends('platform.layout')

@section('title', 'Calendar')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">{{ $month->format('F Y') }}</h1>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"
               class="btn btn-outline-secondary btn-sm">&larr; Prev</a>
            <a href="{{ route('calendar.index') }}" class="btn btn-outline-secondary btn-sm">Today</a>
            <a href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}"
               class="btn btn-outline-secondary btn-sm">Next &rarr;</a>
            @can('calendar.create')
                <a href="{{ route('calendar.events.create') }}" class="btn btn-primary btn-sm">+ New event</a>
            @endcan
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 calendar-grid align-top">
                <thead class="table-light">
                    <tr>
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <th class="text-center small">{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td class="{{ $day['inMonth'] ? '' : 'table-light' }} p-1" style="height: 6rem;">
                                    <div class="d-flex justify-content-between">
                                        <span class="small {{ $day['date']->isToday() ? 'badge text-bg-primary' : 'text-muted' }}">
                                            {{ $day['date']->format('j') }}
                                        </span>
                                        @if ($day['inMonth'])
                                            @can('calendar.create')
                                                <a href="{{ route('calendar.events.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                                   class="text-muted small text-decoration-none">+</a>
                                            @endcan
                                        @endif
                                    </div>
                                    @foreach ($day['events']->take(3) as $event)
                                        <div class="badge text-bg-secondary d-block text-truncate mb-1" title="{{ $event->title }}">
                                            {{ $event->durationLabel() }} {{ $event->title }}
                                        </div>
                                    @endforeach
                                    @if ($day['events']->count() > 3)
                                        <span class="small text-muted">+{{ $day['events']->count() - 3 }} more</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <h2 class="h5">Upcoming events</h2>
            @forelse ($upcoming as $event)
                <div class="card shadow-sm mb-2">
                    <div class="card-body py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $event->title }}</strong>
                            <div class="small text-muted">
                                {{ $event->starts_at->format('D, M j') }} · {{ $event->durationLabel() }}
                                @if ($event->location) · {{ $event->location }} @endif
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            @can('calendar.update')
                                <a href="{{ route('calendar.events.edit', $event) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endcan
                            @can('calendar.delete')
                                <form method="POST" action="{{ route('calendar.events.destroy', $event) }}"
                                      onsubmit="return confirm('Delete this event?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted">Nothing scheduled.</div>
            @endforelse
        </div>
    </div>
@endsection
