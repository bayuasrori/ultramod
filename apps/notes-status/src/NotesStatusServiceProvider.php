<?php

namespace PlatformApps\NotesStatus;

use App\Platform\Contracts\MenuProvider;
use App\Platform\Contracts\Uninstallable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use PlatformApps\NotesStatus\Models\NoteStatus;
use PlatformApps\NotesStatus\Models\NoteStatusAssignment;

class NotesStatusServiceProvider extends ServiceProvider implements MenuProvider, Uninstallable
{
    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'notes-status');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        Route::middleware(['web', 'auth'])->group(dirname(__DIR__).'/routes/web.php');

        $this->registerSlots();
        $this->registerListeners();
        $this->seedStatuses();
    }

    /**
     * Inject status UI into Notes extension slots. Notes has no knowledge
     * of this extension; the slot system is generic.
     */
    protected function registerSlots(): void
    {
        $slots = $this->app->make(\App\Platform\Contracts\ExtensionSlot::class);

        $slots->register('note.metadata', function (array $context) {
            $note = $context['note'] ?? null;
            if (! $note) {
                return '';
            }

            return view('notes-status::slot', [
                'note' => $note,
                'assignment' => NoteStatusAssignment::with('status')->where('note_id', $note->id)->first(),
                'statuses' => NoteStatus::orderBy('position')->get(),
            ])->render();
        });

        $slots->register('note.header', function (array $context) {
            $note = $context['note'] ?? null;
            if (! $note) {
                return '';
            }

            $assignment = NoteStatusAssignment::with('status')->where('note_id', $note->id)->first();

            return $assignment && $assignment->status
                ? '<span class="badge text-bg-'.$assignment->status->color.'">'.$assignment->status->name.'</span>'
                : '';
        });
    }

    /**
     * React to Notes domain events — no coupling back into Notes.
     */
    protected function registerListeners(): void
    {
        Event::listen(\PlatformApps\Notes\Events\NoteCreated::class, function ($event) {
            NoteStatus::seedDefaults();

            $draft = NoteStatus::where('slug', 'draft')->first();

            NoteStatusAssignment::firstOrCreate(
                ['note_id' => $event->note->id],
                [
                    'note_status_id' => $draft?->id,
                    'assigned_by' => auth()->id(),
                ],
            );
        });
    }

    protected function seedStatuses(): void
    {
        try {
            if (Schema::hasTable('note_statuses')) {
                NoteStatus::seedDefaults();
            }
        } catch (\Throwable) {
            // tables not migrated yet
        }
    }

    public function menu(): array
    {
        return [
            ['label' => 'Note Statuses', 'route' => 'notes-status.index'],
        ];
    }

    public function uninstall(): void
    {
        Schema::dropIfExists('note_status_assignments');
        Schema::dropIfExists('note_statuses');
    }
}
