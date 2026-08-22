<?php

namespace PlatformApps\NotesStatus\Models;

use Illuminate\Database\Eloquent\Model;

class NoteStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'position'];

    public static function seedDefaults(): void
    {
        $defaults = [
            ['name' => 'Draft', 'slug' => 'draft', 'color' => 'secondary', 'position' => 0],
            ['name' => 'Review', 'slug' => 'review', 'color' => 'info', 'position' => 1],
            ['name' => 'Published', 'slug' => 'published', 'color' => 'success', 'position' => 2],
            ['name' => 'Archived', 'slug' => 'archived', 'color' => 'light', 'position' => 3],
        ];

        foreach ($defaults as $status) {
            static::firstOrCreate(['slug' => $status['slug']], $status);
        }
    }
}
