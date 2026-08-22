<?php
namespace App\Platform\Services;

use App\Platform\Models\PlatformFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;

class FileManager
{
    public function upload(UploadedFile $file, string $directory = 'attachments', ?Model $attachment = null): PlatformFile
    {
        $path = $file->store($directory, 'local');
        
        return PlatformFile::create([
            'disk' => 'local',
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'owner_id' => auth()->id(),
            'attachment_type' => $attachment ? get_class($attachment) : null,
            'attachment_id' => $attachment ? $attachment->getKey() : null,
        ]);
    }
}
