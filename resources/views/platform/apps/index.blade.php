@extends('platform.layout')

@section('title', 'Apps')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Apps</h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary">{{ $apps->count() }} app{{ $apps->count() === 1 ? '' : 's' }}</span>
            @if ($upgradableCount > 1)
                <button type="button" class="btn btn-sm btn-primary"
                        data-upgrade-plan="{{ route('platform.apps.upgrade-all.plan') }}"
                        data-upgrade-action="{{ route('platform.apps.upgrade-all') }}"
                        data-upgrade-title="Upgrade all apps">
                    Upgrade all ({{ $upgradableCount }})
                </button>
            @endif
            <a href="{{ route('platform.apps.create') }}" class="btn btn-primary btn-sm">+ New app</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>App</th>
                        <th>Name</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($apps as $app)
                    <tr>
                        <td><code>{{ $app->app_id }}</code></td>
                        <td>
                            {{ $app->name }}
                            @if ($app->last_upgrade_error)
                                <div class="small text-danger">Last upgrade failed: {{ $app->last_upgrade_error }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($app->hasUpgrade())
                                <span class="text-muted">{{ $app->version }}</span>
                                <span aria-hidden="true">&rarr;</span>
                                <strong>{{ $app->available_version }}</strong>
                                <span class="badge text-bg-warning">upgrade available</span>
                            @else
                                {{ $app->version }}
                            @endif
                        </td>
                        <td>
                            @switch($app->status)
                                @case('enabled')<span class="badge text-bg-success">enabled</span>@break
                                @case('disabled')<span class="badge text-bg-danger">disabled</span>@break
                                @case('installed')<span class="badge text-bg-info">installed</span>@break
                                @default<span class="badge text-bg-secondary">discovered</span>
                            @endswitch
                        </td>
                        <td class="text-end">
                            @if ($app->hasUpgrade())
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-upgrade-plan="{{ route('platform.apps.upgrade.plan', $app->app_id) }}"
                                        data-upgrade-action="{{ route('platform.apps.upgrade', $app->app_id) }}"
                                        data-upgrade-title="Upgrade {{ $app->name }} to {{ $app->available_version }}">
                                    Upgrade
                                </button>
                            @elseif ($app->isLive())
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-upgrade-plan="{{ route('platform.apps.upgrade.plan', $app->app_id) }}?force=1"
                                        data-upgrade-action="{{ route('platform.apps.upgrade', $app->app_id) }}"
                                        data-upgrade-force="1"
                                        data-upgrade-title="Reapply {{ $app->name }} {{ $app->version }}">
                                    Reapply
                                </button>
                            @endif

                            @if (in_array($app->status, ['discovered', 'disabled']))
                                <form method="POST" action="{{ route('platform.apps.install', $app->app_id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Install</button>
                                </form>
                            @endif

                            @if (in_array($app->status, ['installed', 'disabled']))
                                <form method="POST" action="{{ route('platform.apps.enable', $app->app_id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Enable</button>
                                </form>
                            @endif

                            @if ($app->status === 'enabled')
                                <form method="POST" action="{{ route('platform.apps.disable', $app->app_id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">Disable</button>
                                </form>
                            @endif

                            @if ($app->status === 'disabled')
                                <form method="POST" action="{{ route('platform.apps.uninstall', $app->app_id) }}" class="d-inline"
                                      onsubmit="return confirm('Uninstall {{ $app->app_id }}? This runs app cleanup (e.g. drops its tables).')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Uninstall</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No apps discovered yet. Run <code>php artisan platform:app:discover</code>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('platform.apps.upgrade-modal')
@endsection
