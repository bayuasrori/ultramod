<?php

namespace PlatformApps\NotesStatus\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PlatformApps\NotesStatus\Models\NoteStatus;
use PlatformApps\NotesStatus\Models\NoteStatusAssignment;

class NoteStatusController extends Controller
{
    public function index()
    {
        $statuses = NoteStatus::withCount('assignments')->orderBy('position')->get();

        return view('notes-status::index', ['statuses' => $statuses]);
    }

    public function notes(NoteStatus $status)
    {
        $noteIds = NoteStatusAssignment::where('note_status_id', $status->id)->pluck('note_id');

        $notes = \PlatformApps\Notes\Models\Note::whereIn('id', $noteIds)
            ->latest('id')
            ->get();

        return view('notes-status::notes', ['status' => $status, 'notes' => $notes]);
    }

    public function assign(Request $request, int $noteId, AuditLogger $audit)
    {
        $validated = $request->validate([
            'note_status_id' => ['required', 'integer', 'exists:note_statuses,id'],
        ]);

        // Interact with Notes through its public model, not its internals.
        $note = \PlatformApps\Notes\Models\Note::findOrFail($noteId);

        NoteStatusAssignment::updateOrCreate(
            ['note_id' => $note->id],
            [
                'note_status_id' => $validated['note_status_id'],
                'assigned_by' => auth()->id(),
            ],
        );

        $status = NoteStatus::find($validated['note_status_id']);
        $audit->log('notes-status.assigned', target: $note, metadata: ['status' => $status?->slug]);

        return redirect()->back();
    }
}
