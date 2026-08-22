<?php

namespace PlatformApps\Bookmarks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PlatformApps\Bookmarks\Models\Bookmark;

class FetchBookmarkMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 20;

    public function __construct(public Bookmark $bookmark)
    {
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'UltramodBookmarks/1.0'])
                ->get($this->bookmark->url);

            if (! $response->successful()) {
                $this->markFetched();
                return;
            }

            $html = $response->body();

            $this->bookmark->update([
                'title' => $this->meta($html, 'title') ?: $this->bookmark->title,
                'description' => $this->meta($html, 'description') ?: $this->bookmark->description,
                'site_name' => $this->meta($html, 'og:site_name') ?: $this->bookmark->host(),
                'favicon_url' => 'https://www.google.com/s2/favicons?domain='.$this->bookmark->host(),
                'metadata_fetched_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::info('Bookmark metadata fetch failed', [
                'url' => $this->bookmark->url,
                'error' => $e->getMessage(),
            ]);

            $this->markFetched();
        }
    }

    protected function markFetched(): void
    {
        $this->bookmark->update(['metadata_fetched_at' => now()]);
    }

    protected function meta(string $html, string $name): ?string
    {
        // <title>...</title>
        if ($name === 'title') {
            return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)
                ? trim(html_entity_decode($m[1]))
                : null;
        }

        // <meta name="description"|property="og:description" content="...">
        $pattern = '/<meta[^>]+(?:name|property)=["\']'.preg_quote($name, '/').'["\'][^>]*content=["\'](.*?)["\']/is';
        if (preg_match($pattern, $html, $m)) {
            return trim(html_entity_decode($m[1]));
        }

        // attribute order reversed: content before name/property
        $pattern = '/<meta[^>]+content=["\'](.*?)["\'][^>]*(?:name|property)=["\']'.preg_quote($name, '/').'["\']/is';
        if (preg_match($pattern, $html, $m)) {
            return trim(html_entity_decode($m[1]));
        }

        return null;
    }
}
