<?php

namespace PlatformApps\Notes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Note extends Model
{
    protected $table = 'notes';

    protected $fillable = ['title', 'content', 'created_by'];

    public function revisions(): HasMany
    {
        return $this->hasMany(NoteRevision::class)->latest('id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function tagStrings(): array
    {
        return DB::table('note_tags')->where('note_id', $this->id)->orderBy('tag')->pluck('tag')->all();
    }

    public function syncTags(array|string|null $tags): void
    {
        DB::table('note_tags')->where('note_id', $this->id)->delete();

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $unique = array_unique(array_filter(array_map('trim', (array) $tags)));
        foreach ($unique as $tag) {
            DB::table('note_tags')->insert(['note_id' => $this->id, 'tag' => $tag]);
        }
    }

    public function html(): string
    {
        return str($this->content)->markdown([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function excerpt(int $limit = 120): string
    {
        return str($this->content)->limit($limit);
    }

    public function attachments()
    {
        return \App\Platform\Models\PlatformFile::where('attachment_type', static::class)
            ->where('attachment_id', $this->id)
            ->latest('id');
    }
}
