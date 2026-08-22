@extends('platform.layout')

@section('title', 'Unauthorized')

@section('content')
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <h1 class="display-6">403</h1>
            <p class="lead">You do not have permission to access this application.</p>
            <p class="text-muted">
                Ask an administrator to grant your role the required permission,
                or go back to the <a href="{{ route('platform.index') }}">dashboard</a>.
            </p>
            @auth
                <p class="small text-muted">
                    Your role: <code>{{ auth()->user()->role?->name ?? 'none' }}</code> —
                    manage permissions at <code>/platform/roles</code> (admin only).
                </p>
            @endauth
        </div>
    </div>
@endsection
