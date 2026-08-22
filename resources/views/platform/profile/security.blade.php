@extends('platform.layout')

@section('title', 'Login history')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Login history</h1>
        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Back to profile</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Result</th>
                        <th>IP address</th>
                        <th>User agent</th>
                        <th>Logout</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($history as $entry)
                    <tr>
                        <td>{{ $entry->login_at->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if ($entry->successful)
                                <span class="badge text-bg-success">success</span>
                            @else
                                <span class="badge text-bg-danger">failed</span>
                            @endif
                        </td>
                        <td><code>{{ $entry->ip_address }}</code></td>
                        <td class="text-truncate" style="max-width: 22rem;">
                            <small class="text-muted">{{ $entry->user_agent }}</small>
                        </td>
                        <td>{{ $entry->logout_at?->format('H:i:s') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No login history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
