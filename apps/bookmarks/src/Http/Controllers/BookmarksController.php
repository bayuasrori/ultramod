<?php

namespace PlatformApps\Bookmarks\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use PlatformApps\Bookmarks\Jobs\FetchBookmarkMetadata;
use PlatformApps\Bookmarks\Models\Bookmark;

class BookmarksController extends Controller
{
    public function index(Request $request)
    {
        $bookmarks = Bookmark::query()
            ->when($request->filled('q'), fn ($q) => $q->search($request->input('q')))
            ->when($request->filled('collection'), fn ($q) => $q->where('collection', $request->input('collection')))
            ->when($request->boolean('favorites'), fn ($q) => $q->where('is_favorite', true))
            ->when($request->filled('tag'), fn ($q) => $q->whereIn(
                'id',
                \DB::table('bookmark_tag')->where('tag', $request->input('tag'))->pluck('bookmark_id')
            ))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('bookmarks::index', [
            'bookmarks' => $bookmarks,
            'collections' => Bookmark::whereNotNull('collection')->distinct()->pluck('collection')->sort()->values(),
            'tags' => \DB::table('bookmark_tag')->distinct()->pluck('tag')->sort()->values(),
            'filters' => $request->only(['q', 'collection', 'tag', 'favorites']),
        ]);
    }

    public function create()
    {
        return view('bookmarks::form', [
            'bookmark' => new Bookmark(),
            'collections' => Bookmark::whereNotNull('collection')->distinct()->pluck('collection')->sort(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'collection' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $bookmark = Bookmark::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);
        $bookmark->syncTags($validated['tags'] ?? '');

        // Enrich title/description/favicon asynchronously.
        FetchBookmarkMetadata::dispatch($bookmark);

        $audit->log('bookmarks.created', target: $bookmark);

        return redirect()->route('bookmarks.index');
    }

    public function edit(Bookmark $bookmark)
    {
        return view('bookmarks::form', [
            'bookmark' => $bookmark,
            'collections' => Bookmark::whereNotNull('collection')->distinct()->pluck('collection')->sort(),
            'tags' => implode(', ', $bookmark->tagStrings()),
        ]);
    }

    public function update(Request $request, Bookmark $bookmark, AuditLogger $audit)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'collection' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $urlChanged = $bookmark->url !== $validated['url'];

        $bookmark->update($validated);
        $bookmark->syncTags($validated['tags'] ?? '');

        if ($urlChanged) {
            $bookmark->update(['metadata_fetched_at' => null]);
            FetchBookmarkMetadata::dispatch($bookmark);
        }

        $audit->log('bookmarks.updated', target: $bookmark);

        return redirect()->route('bookmarks.index');
    }

    public function destroy(Bookmark $bookmark, AuditLogger $audit)
    {
        $audit->log('bookmarks.deleted', metadata: ['url' => $bookmark->url]);
        $bookmark->delete();

        return redirect()->route('bookmarks.index');
    }

    public function toggleFavorite(Bookmark $bookmark)
    {
        $bookmark->update(['is_favorite' => ! $bookmark->is_favorite]);

        return redirect()->back();
    }
}
