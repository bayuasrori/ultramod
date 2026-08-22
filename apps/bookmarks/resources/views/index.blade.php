@extends('platform.layout')

@section('title', 'Bookmarks')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Bookmarks</h1>
        @can('bookmarks.create')
            <a href="{{ route('bookmarks.create') }}" class="btn btn-primary">+ New bookmark</a>
        @endcan
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('bookmarks.index') }}">
                        <label class="form-label small fw-semibold text-uppercase text-muted">Search</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}"
                                   placeholder="title, url, tag...">
                            <button class="btn btn-outline-primary">Go</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="small fw-semibold text-uppercase text-muted mb-2">Collections</div>
                    <div class="d-flex flex-column gap-1">
                        <a href="{{ route('bookmarks.index') }}"
                           class="text-decoration-none {{ empty($filters['collection']) && empty($filters['tag']) && empty($filters['favorites']) ? 'fw-bold' : '' }}">
                            All
                        </a>
                        @foreach ($collections as $collection)
                            <a href="{{ route('bookmarks.index', ['collection' => $collection]) }}"
                               class="text-decoration-none {{ ($filters['collection'] ?? '') === $collection ? 'fw-bold' : '' }}">
                                {{ $collection }}
                            </a>
                        @endforeach
                    </div>

                    <div class="small fw-semibold text-uppercase text-muted mt-3 mb-2">Tags</div>
                    @forelse ($tags as $tag)
                        <a href="{{ route('bookmarks.index', ['tag' => $tag]) }}" class="badge text-bg-light border text-decoration-none me-1">
                            {{ $tag }}
                        </a>
                    @empty
                        <span class="text-muted small">No tags yet</span>
                    @endforelse

                    <div class="mt-3">
                        <a href="{{ route('bookmarks.index', ['favorites' => 1]) }}"
                           class="btn btn-sm {{ ! empty($filters['favorites']) ? 'btn-warning' : 'btn-outline-warning' }}">
                            Favorites only
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            @forelse ($bookmarks as $bookmark)
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    @if ($bookmark->favicon_url)
                                        <img src="{{ $bookmark->favicon_url }}" alt="" width="16" height="16"
                                             onerror="this.style.display='none'">
                                    @endif
                                    <a href="{{ $bookmark->url }}" target="_blank" rel="noopener"
                                       class="fw-semibold text-decoration-none">{{ $bookmark->title }}</a>
                                    @if ($bookmark->is_favorite)
                                        <span class="badge text-bg-warning">favorite</span>
                                    @endif
                                    @unless ($bookmark->metadata_fetched_at)
                                        <span class="badge text-bg-light border" title="Metadata queued for fetching">fetching…</span>
                                    @endunless
                                </div>
                                <div class="small text-muted">{{ $bookmark->host() }}@if ($bookmark->site_name) · {{ $bookmark->site_name }}@endif</div>
                                @if ($bookmark->description)
                                    <p class="small mb-1 mt-1">{{ $bookmark->description }}</p>
                                @endif
                                <div class="mt-1">
                                    @if ($bookmark->collection)
                                        <a href="{{ route('bookmarks.index', ['collection' => $bookmark->collection]) }}"
                                           class="badge text-bg-primary text-decoration-none">{{ $bookmark->collection }}</a>
                                    @endif
                                    @foreach ($bookmark->tagStrings() as $tag)
                                        <a href="{{ route('bookmarks.index', ['tag' => $tag]) }}"
                                           class="badge text-bg-light border text-decoration-none">{{ $tag }}</a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-1 ms-2">
                                @can('bookmarks.update')
                                    <form method="POST" action="{{ route('bookmarks.favorite', $bookmark) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm {{ $bookmark->is_favorite ? 'btn-warning' : 'btn-outline-warning' }}"
                                                title="Toggle favorite">Star</button>
                                    </form>
                                    <a href="{{ route('bookmarks.edit', $bookmark) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endcan
                                @can('bookmarks.delete')
                                    <form method="POST" action="{{ route('bookmarks.destroy', $bookmark) }}"
                                          onsubmit="return confirm('Delete this bookmark?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-light text-center">No bookmarks found.</div>
            @endforelse

            <div class="mt-3">{{ $bookmarks->links() }}</div>
        </div>
    </div>
@endsection
