<?php

namespace PlatformApps\Bookmarks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Bookmark extends Model
{
    protected $table = 'bookmarks';

    protected $fillable = [
        'url', 'title', 'description', 'collection', 'is_favorite',
        'favicon_url', 'site_name', 'metadata_fetched_at', 'created_by',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'metadata_fetched_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function tagStrings(): array
    {
        return DB::table('bookmark_tag')->where('bookmark_id', $this->id)->orderBy('tag')->pluck('tag')->all();
    }

    public function syncTags(array|string|null $tags): void
    {
        DB::table('bookmark_tag')->where('bookmark_id', $this->id)->delete();

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $unique = array_unique(array_filter(array_map('trim', (array) $tags)));
        foreach ($unique as $tag) {
            DB::table('bookmark_tag')->insert(['bookmark_id' => $this->id, 'tag' => $tag]);
        }
    }

    public function scopeSearch($query, ?string $q)
    {
        if (! $q) {
            return $query;
        }

        return $query->where(function ($inner) use ($q) {
            $inner->where('title', 'like', "%{$q}%")
                ->orWhere('url', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhereIn('id', DB::table('bookmark_tag')
                    ->where('tag', 'like', "%{$q}%")
                    ->pluck('bookmark_id'));
        });
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }
}
