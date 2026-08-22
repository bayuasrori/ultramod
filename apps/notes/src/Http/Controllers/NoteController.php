<?php

namespace PlatformApps\Notes\Http\Controllers;

use App\Platform\Services\AuditLogger;
use App\Platform\Services\FileManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use PlatformApps\Notes\Events\NoteCreated;
use PlatformApps\Notes\Events\NoteDeleted;
use PlatformApps\Notes\Events\NoteUpdated;
use PlatformApps\Notes\Http\Requests\StoreNoteRequest;
use PlatformApps\Notes\Models\Note;
use PlatformApps\Notes\Models\NoteRevision;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = Note::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->input('q');

                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%")
                        ->orWhereIn('id', \DB::table('note_tags')
                            ->where('tag', 'like', "%{$term}%")
                            ->pluck('note_id'));
                });
            })
            ->withCount('revisions')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('notes::index', [
            'notes' => $notes,
            'q' => $request->input('q', ''),
        ]);
    }

    public function create()
    {
        return view('notes::form', ['note' => new Note(), 'tags' => '']);
    }

    public function store(StoreNoteRequest $request, AuditLogger $audit)
    {
        $note = Note::create([
            ...$request->only('title', 'content'),
            'created_by' => auth()->id(),
        ]);
        $note->syncTags($request->input('tags', ''));

        NoteCreated::dispatch($note);
        $audit->log('notes.created', target: $note);

        return redirect()->route('notes.show', $note);
    }

    public function show(Note $note)
    {
        return view('notes::show', [
            'note' => $note,
            'attachments' => $note->attachments()->get(),
            'revisions' => $note->revisions()->limit(10)->get(),
        ]);
    }

    public function edit(Note $note)
    {
        return view('notes::form', [
            'note' => $note,
            'tags' => implode(', ', $note->tagStrings()),
        ]);
    }

    public function update(StoreNoteRequest $request, Note $note, AuditLogger $audit)
    {
        // snapshot current state as a revision before overwriting
        $note->revisions()->create([
            'title' => $note->title,
            'content' => $note->content,
            'created_by' => auth()->id(),
        ]);

        $note->update($request->only('title', 'content'));
        $note->syncTags($request->input('tags', ''));

        NoteUpdated::dispatch($note);
        $audit->log('notes.updated', target: $note);

        return redirect()->route('notes.show', $note);
    }

    public function destroy(Note $note, AuditLogger $audit)
    {
        NoteDeleted::dispatch($note);
        $audit->log('notes.deleted', metadata: ['title' => $note->title]);
        $note->delete();

        return redirect()->route('notes.index');
    }

    public function restoreRevision(Note $note, NoteRevision $revision, AuditLogger $audit)
    {
        abort_unless($revision->note_id === $note->id, 404);

        $note->revisions()->create([
            'title' => $note->title,
            'content' => $note->content,
            'created_by' => auth()->id(),
        ]);

        $note->update([
            'title' => $revision->title,
            'content' => $revision->content,
        ]);

        NoteUpdated::dispatch($note);
        $audit->log('notes.revision_restored', target: $note, metadata: ['revision_id' => $revision->id]);

        return redirect()->route('notes.show', $note)->with('status', 'Revision restored.');
    }

    public function uploadAttachment(Request $request, Note $note, FileManager $files, AuditLogger $audit)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $files->upload($validated['file'], "notes/{$note->id}", $note);
        $audit->log('notes.attachment_uploaded', target: $note);

        return redirect()->route('notes.show', $note)->with('status', 'Attachment uploaded.');
    }

    public function downloadAttachment(Note $note, int $fileId)
    {
        $file = $note->attachments()->findOrFail($fileId);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function deleteAttachment(Note $note, int $fileId, AuditLogger $audit)
    {
        $file = $note->attachments()->findOrFail($fileId);

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        $audit->log('notes.attachment_deleted', target: $note, metadata: ['file' => $file->name]);

        return redirect()->route('notes.show', $note)->with('status', 'Attachment deleted.');
    }
}
