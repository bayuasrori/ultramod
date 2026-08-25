@extends('platform.layout')

@section('title', 'Home')

@section('content')
    <div class="launcher">
        <div class="d-flex align-items-end justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <div class="launcher-greeting">{{ $greeting }}, {{ auth()->user()->name }}</div>
                <div class="text-muted small">
                    {{ $apps->count() }} app{{ $apps->count() === 1 ? '' : 's' }} installed
                    @if ($upgradableCount > 0)
                        · <span class="text-warning-emphasis">{{ $upgradableCount }} update{{ $upgradableCount === 1 ? '' : 's' }} available</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @can('platform.manage')
                    @if ($upgradableCount > 1)
                        <button type="button" class="btn btn-sm btn-primary"
                                data-upgrade-plan="{{ route('platform.apps.upgrade-all.plan') }}"
                                data-upgrade-action="{{ route('platform.apps.upgrade-all') }}"
                                data-upgrade-title="Upgrade all apps">
                            Update all ({{ $upgradableCount }})
                        </button>
                    @endif
                @endcan
            </div>
        </div>

        @if ($apps->isNotEmpty())
            <div class="launcher-search mb-4">
                <input type="search" id="launcher-filter" class="form-control form-control-lg"
                       placeholder="Search apps…" autocomplete="off" aria-label="Search apps">
            </div>
        @endif

        <div class="launcher-grid" id="launcher-grid">
            @forelse ($apps as $card)
                @php($app = $card['app'])
                @php($launchable = (bool) $card['entry'])

                <div class="launcher-cell" data-name="{{ Str::lower($app->name.' '.$app->app_id) }}">
                    <a class="launcher-tile {{ $launchable ? '' : 'is-inactive' }}"
                       href="{{ $launchable ? route($card['entry']) : '#' }}"
                       @unless($launchable) aria-disabled="true" tabindex="-1" @endunless
                       title="{{ $card['description'] ?: $app->name }}">
                        <span class="launcher-icon" style="--tile: {{ $app->tileColor() }}">
                            {{ $app->iconLabel() }}
                            @if ($app->hasUpgrade())
                                <span class="launcher-dot" title="Update available"></span>
                            @endif
                        </span>
                        <span class="launcher-label">{{ $app->name }}</span>
                    </a>

                    {{-- A pending update is actionable, so it outranks the
                         "why can't I open this" note underneath the tile. --}}
                    @if ($app->hasUpgrade() && auth()->user()->can('platform.manage'))
                        <button type="button" class="launcher-note launcher-note-action"
                                data-upgrade-plan="{{ route('platform.apps.upgrade.plan', $app->app_id) }}"
                                data-upgrade-action="{{ route('platform.apps.upgrade', $app->app_id) }}"
                                data-upgrade-title="Upgrade {{ $app->name }} to {{ $app->available_version }}">
                            update to {{ $app->available_version }}
                        </button>
                    @elseif (! $launchable)
                        <span class="launcher-note">{{ $app->status === 'disabled' ? 'disabled' : 'no entry page' }}</span>
                    @endif
                </div>
            @empty
                <div class="launcher-empty">
                    <p class="mb-2 text-muted">No apps installed yet.</p>
                    @can('platform.manage')
                        <a href="{{ route('platform.apps.index') }}" class="btn btn-primary btn-sm">Browse apps</a>
                    @endcan
                </div>
            @endforelse

            @can('platform.manage')
                <div class="launcher-cell" data-name="apps manage install settings">
                    <a class="launcher-tile" href="{{ route('platform.apps.index') }}" title="Install, enable and upgrade apps">
                        <span class="launcher-icon launcher-icon-ghost">+</span>
                        <span class="launcher-label">Apps</span>
                    </a>
                    <span class="launcher-note">manage</span>
                </div>
            @endcan
        </div>

        <p class="text-muted small mt-4 mb-0 d-none" id="launcher-no-match">No app matches that search.</p>
    </div>

    @include('platform.apps.upgrade-modal')
@endsection

@push('styles')
<style>
    .launcher-greeting {
        font-size: 1.6rem;
        font-weight: 600;
        letter-spacing: -0.02em;
    }

    .launcher-search input {
        max-width: 26rem;
        border-radius: 999px;
        padding-inline: 1.1rem;
    }

    .launcher-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(6.5rem, 1fr));
        gap: 1.75rem 1rem;
    }

    .launcher-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-width: 0;
    }

    .launcher-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .55rem;
        width: 100%;
        text-decoration: none;
        color: inherit;
        border-radius: 1rem;
        padding: .35rem;
        transition: transform .12s ease;
    }

    .launcher-tile:hover,
    .launcher-tile:focus-visible {
        transform: translateY(-3px);
        color: inherit;
    }

    .launcher-tile:active { transform: translateY(0) scale(.96); }

    .launcher-icon {
        position: relative;
        display: grid;
        place-items: center;
        width: 4.25rem;
        height: 4.25rem;
        /* The squircle is what makes a grid of links read as a home screen. */
        border-radius: 30%;
        background: linear-gradient(160deg, color-mix(in srgb, var(--tile) 82%, white), var(--tile));
        color: #fff;
        font-size: 1.75rem;
        font-weight: 600;
        line-height: 1;
        box-shadow: 0 6px 14px -6px color-mix(in srgb, var(--tile) 70%, transparent);
    }

    .launcher-icon-ghost {
        background: var(--bs-body-bg);
        color: var(--bs-secondary-color);
        border: 2px dashed var(--bs-border-color);
        box-shadow: none;
        font-weight: 400;
    }

    .launcher-dot {
        position: absolute;
        top: -.15rem;
        right: -.15rem;
        width: .95rem;
        height: .95rem;
        border-radius: 50%;
        background: var(--bs-warning);
        border: 2px solid var(--bs-body-bg);
    }

    .launcher-label {
        font-size: .82rem;
        line-height: 1.2;
        width: 100%;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        word-break: break-word;
    }

    .launcher-note {
        font-size: .68rem;
        color: var(--bs-secondary-color);
        margin-top: .2rem;
        background: none;
        border: 0;
        padding: 0;
    }

    .launcher-note-action { color: var(--bs-warning-text-emphasis); text-decoration: underline; }

    .launcher-tile.is-inactive { opacity: .45; pointer-events: none; }

    .launcher-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        border: 1px dashed var(--bs-border-color);
        border-radius: 1rem;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var filter = document.getElementById('launcher-filter');
    var empty = document.getElementById('launcher-no-match');

    if (!filter) {
        return;
    }

    filter.addEventListener('input', function () {
        var needle = filter.value.trim().toLowerCase();
        var visible = 0;

        document.querySelectorAll('#launcher-grid .launcher-cell').forEach(function (cell) {
            var match = needle === '' || cell.dataset.name.indexOf(needle) !== -1;
            cell.style.display = match ? '' : 'none';
            visible += match ? 1 : 0;
        });

        empty.classList.toggle('d-none', visible > 0);
    });
})();
</script>
@endpush
