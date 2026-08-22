@extends('hello::layout')

@section('title', 'Hello App')

@section('content')
    <div class="card shadow-sm" style="max-width: 32rem;">
        <div class="card-body">
            <h1 class="h3 card-title">Hello from Platform App!</h1>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>App</span><code>{{ $app->app_id }}</code></li>
                <li class="list-group-item d-flex justify-content-between"><span>Version</span><code>{{ $app->version }}</code></li>
                <li class="list-group-item d-flex justify-content-between"><span>Status</span><span class="badge text-bg-success">{{ $app->status }}</span></li>
            </ul>
        </div>
    </div>
@endsection
