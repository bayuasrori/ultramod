@extends('platform.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Dashboard</h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary">{{ $apps->count() }} installed</span>
            @can('platform.manage')
                @if ($upgradableCount > 1)
                    <button type="button" class="btn btn-sm btn-primary"
                            data-upgrade-plan="{{ route('platform.apps.upgrade-all.plan') }}"
                            data-upgrade-action="{{ route('platform.apps.upgrade-all') }}"
                            data-upgrade-title="Upgrade all apps">
                        Upgrade all ({{ $upgradableCount }})
                    </button>
                @endif
                <a href="{{ route('platform.apps.index') }}" class="btn btn-outline-primary btn-sm">Manage apps</a>
            @endcan
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
        @forelse ($apps as $card)
            @php($app = $card['app'])
            <div class="col">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h2 class="h5 card-title mb-1">{{ $app->name }}</h2>
                            @switch($app->status)
                                @case('enabled')<span class="badge text-bg-success">enabled</span>@break
                                @case('disabled')<span class="badge text-bg-danger">disabled</span>@break
                                @default<span class="badge text-bg-info">installed</span>
                            @endswitch
                        </div>
                        <div class="small text-muted mb-2">
                            <code>{{ $app->app_id }}</code>
                            @if ($app->hasUpgrade())
                                &middot; <span class="text-muted">{{ $app->version }}</span>
                                <span aria-hidden="true">&rarr;</span>
                                <strong>{{ $app->available_version }}</strong>
                                <span class="badge text-bg-warning">upgrade available</span>
                            @else
                                &middot; {{ $app->version }}
                            @endif
                        </div>
                        @if ($card['description'])
                            <p class="card-text small mb-0">{{ $card['description'] }}</p>
                        @endif
                        @if ($app->last_upgrade_error)
                            <div class="small text-danger mt-2">Last upgrade failed: {{ $app->last_upgrade_error }}</div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent d-flex gap-2">
                        @if ($card['entry'])
                            <a href="{{ route($card['entry']) }}" class="btn btn-sm btn-primary">Open</a>
                        @elseif ($app->status === 'disabled')
                            <span class="btn btn-sm btn-outline-secondary disabled">Disabled</span>
                        @else
                            <span class="btn btn-sm btn-outline-secondary disabled">No entry page</span>
                        @endif

                        @can('platform.manage')
                            @if ($app->hasUpgrade())
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-upgrade-plan="{{ route('platform.apps.upgrade.plan', $app->app_id) }}"
                                        data-upgrade-action="{{ route('platform.apps.upgrade', $app->app_id) }}"
                                        data-upgrade-title="Upgrade {{ $app->name }} to {{ $app->available_version }}">
                                    Upgrade
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm text-center text-muted py-5">
                    <div class="card-body">
                        <p class="mb-1">No apps installed yet.</p>
                        @can('platform.manage')
                            <a href="{{ route('platform.apps.index') }}" class="btn btn-primary btn-sm">Install an app</a>
                        @endcan
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @include('platform.apps.upgrade-modal')
@endsection
