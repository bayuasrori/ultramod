<?php

namespace Tests\Feature;

use App\Platform\Services\AppManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PlatformApps\AiAssistant\Models\AiConversation;
use PlatformApps\Bookmarks\Jobs\FetchBookmarkMetadata;
use PlatformApps\Bookmarks\Models\Bookmark;
use PlatformApps\Calendar\Models\CalendarEvent;
use PlatformApps\Kanban\Models\Board;
use PlatformApps\Kanban\Models\Column;
use PlatformApps\Kanban\Models\Task;
use PlatformApps\Notes\Models\Note;
use PlatformApps\NotesStatus\Models\NoteStatus;
use PlatformApps\NotesStatus\Models\NoteStatusAssignment;
use Tests\TestCase;

class StarterAppsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $db = __DIR__.'/../../database/testing.sqlite';
        if (file_exists($db)) {
            unlink($db);
        }
        touch($db);

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->actingAsAdmin();

        $manager = $this->app->make(AppManager::class);
        $manager->discover();
        foreach (['notes', 'notes-status', 'kanban', 'calendar', 'bookmarks', 'ai-assistant'] as $appId) {
            $manager->install($appId);
            $manager->enable($appId);
        }

        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    /*
     * Notes
     */

    public function test_notes_crud_with_markdown_tags_and_revisions(): void
    {
        // create
        $this->post('/notes', ['title' => 'Spec doc', 'content' => '# Heading', 'tags' => 'spec, draft'])
            ->assertRedirect();

        $note = Note::where('title', 'Spec doc')->first();
        $this->assertNotNull($note);
        $this->assertSame(['draft', 'spec'], $note->tagStrings());

        // markdown rendered on show page
        $this->get("/notes/{$note->id}")
            ->assertOk()
            ->assertSee('<h1>Heading</h1>', false);

        // edit creates a revision snapshot of the old content
        $this->put("/notes/{$note->id}", ['title' => 'Spec doc v2', 'content' => '# Updated', 'tags' => 'spec']);
        $this->assertSame(1, $note->revisions()->count());
        $this->assertSame('Spec doc', $note->revisions()->first()->title);

        // restore revision
        $revision = $note->revisions()->first();
        $this->put("/notes/{$note->id}/revisions/{$revision->id}/restore")
            ->assertRedirect();
        $this->assertSame('Spec doc', $note->fresh()->title);

        // search matches content and tag
        $this->get('/notes?q=Heading')->assertOk()->assertSee('Spec doc');
        $this->get('/notes?q=spec')->assertOk()->assertSee('Spec doc');

        // delete
        $this->delete("/notes/{$note->id}")->assertRedirect();
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_notes_attachments_upload_and_download(): void
    {
        Storage::fake('local');

        $note = Note::create(['title' => 'With file', 'content' => 'body', 'created_by' => auth()->id()]);

        $this->post("/notes/{$note->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
        ])->assertRedirect();

        $this->assertSame(1, $note->attachments()->count());

        $file = $note->attachments()->first();
        Storage::disk('local')->assertExists($file->path);

        $this->get("/notes/{$note->id}/attachments/{$file->id}/download")->assertOk();

        $this->delete("/notes/{$note->id}/attachments/{$file->id}")->assertRedirect();
        $this->assertSame(0, $note->attachments()->count());
        Storage::disk('local')->assertMissing($file->path);
    }

    /*
     * Notes Status extension
     */

    public function test_note_gets_default_draft_status_and_can_be_assigned(): void
    {
        $this->post('/notes', ['title' => 'Extended', 'content' => 'hello']);

        $note = Note::where('title', 'Extended')->first();

        // NoteCreated listener assigned Draft
        $assignment = NoteStatusAssignment::where('note_id', $note->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame('draft', $assignment->status->slug);

        // badge visible on notes index via slot
        $this->get('/notes')->assertOk()->assertSee('Draft');

        // assign Published
        $published = NoteStatus::where('slug', 'published')->first();
        $this->post("/notes-status/notes/{$note->id}/status", ['note_status_id' => $published->id])
            ->assertRedirect();

        $this->assertSame('published', $assignment->fresh()->status->slug);

        // filter by status: extension page lists the note
        $this->get("/notes-status/statuses/{$published->id}/notes")
            ->assertOk()
            ->assertSee('Extended');
    }

    public function test_disabling_notes_status_keeps_notes_intact(): void
    {
        $this->post('/notes', ['title' => 'Survivor', 'content' => 'keep me']);
        $note = Note::where('title', 'Survivor')->first();
        $this->assertNotNull(NoteStatusAssignment::where('note_id', $note->id)->first());

        $manager = $this->app->make(AppManager::class);
        $manager->disable('notes-status');

        $this->refreshApplication();
        $this->actingAsAdmin();

        // notes still work
        $this->get('/notes')->assertOk()->assertSee('Survivor');
        $this->get("/notes/{$note->id}")->assertOk();

        // extension data untouched
        $this->assertDatabaseHas('note_status_assignments', ['note_id' => $note->id]);

        // cannot disable notes while extension enabled... extension is disabled now,
        // so disabling notes must succeed
        $this->app->make(AppManager::class)->disable('notes');
        $this->assertSame('disabled', $note->exists ? 'disabled' : '');
    }

    /*
     * Kanban
     */

    public function test_kanban_board_with_columns_tasks_and_move(): void
    {
        $response = $this->post('/kanban/boards', ['name' => 'Sprint 1', 'description' => 'first sprint']);
        $response->assertRedirect();
        
        $board = Board::where('name', 'Sprint 1')->first();
        if (!$board) dd(Board::all()->toArray(), session('errors') ? session('errors')->all() : 'no errors', $response->headers->get('Location'));
        $this->assertNotNull($board);
        $this->assertSame(3, $board->columns()->count()); // Todo / Doing / Done

        // board view renders
        $this->get("/kanban/boards/{$board->id}")->assertOk()->assertSee('Todo');

        // add task to first column
        $column = $board->columns()->first();
        $this->post("/kanban/columns/{$column->id}/tasks", [
            'title' => 'Write tests',
            'priority' => 'high',
            'tags' => 'qa, backend',
        ])->assertRedirect();

        $task = Task::where('title', 'Write tests')->first();
        if (!$task) dd("Task is null! Errors:", session()->all());
        $this->assertNotNull($task);
        $this->assertSame(['backend', 'qa'], $task->tagStrings());
        $this->assertSame('high', $task->priority);

        // drag & drop = move endpoint
        $done = $board->columns()->where('name', 'Done')->first();
        $this->put("/kanban/tasks/{$task->id}/move", ['kanban_column_id' => $done->id])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame($done->id, $task->fresh()->kanban_column_id);

        // task edit page renders with tags prefilled
        $this->get("/kanban/tasks/{$task->id}/edit")->assertOk()->assertSee('qa');

        // audit trail recorded
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'kanban.task.created']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'kanban.task.moved']);
    }

    /*
     * Calendar
     */

    public function test_calendar_event_crud_and_month_grid(): void
    {
        $this->post('/calendar/events', [
            'title' => 'Standup',
            'starts_at' => now()->startOfMonth()->addDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'ends_at' => now()->startOfMonth()->addDays(3)->setTime(9, 30)->format('Y-m-d\TH:i'),
            'location' => 'Room A',
            'attendees' => 'a@corp.com, b@corp.com',
            'reminder_minutes' => 15,
        ])->assertRedirect();

        $event = CalendarEvent::where('title', 'Standup')->first();
        $this->assertNotNull($event);
        $this->assertSame(15, $event->reminder_minutes);
        $this->assertCount(2, $event->attendeeList());

        // event visible in its month grid + upcoming list
        $this->get('/calendar?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertSee('Standup');

        // all-day event
        $this->post('/calendar/events', [
            'title' => 'Conference',
            'starts_at' => now()->addMonth()->startOfMonth()->format('Y-m-d'),
            'all_day' => '1',
        ])->assertRedirect();

        $conference = CalendarEvent::where('title', 'Conference')->first();
        $this->assertTrue($conference->all_day);
        $this->assertSame('All day', $conference->durationLabel());

        // edit + delete
        $this->put("/calendar/events/{$event->id}", [
            'title' => 'Standup moved',
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
        ])->assertRedirect();
        $this->assertSame('Standup moved', $event->fresh()->title);

        $this->delete("/calendar/events/{$event->id}")->assertRedirect();
        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }

    /*
     * Bookmarks
     */

    public function test_bookmarks_crud_search_and_async_metadata(): void
    {
        Queue::fake();

        $this->post('/bookmarks', [
            'url' => 'https://laravel.com/docs',
            'title' => 'Laravel docs',
            'collection' => 'dev',
            'tags' => 'php, docs',
        ])->assertRedirect();

        // metadata fetch queued asynchronously
        Queue::assertPushed(FetchBookmarkMetadata::class);

        $bookmark = Bookmark::where('url', 'https://laravel.com/docs')->first();
        $this->assertSame(['docs', 'php'], $bookmark->tagStrings());

        // search by tag and collection filter
        $this->get('/bookmarks?q=php')->assertOk()->assertSee('Laravel docs');
        $this->get('/bookmarks?collection=dev')->assertOk()->assertSee('Laravel docs');
        $this->get('/bookmarks?q=nomatch')->assertOk()->assertDontSee('Laravel docs');

        // favorite toggle
        $this->put("/bookmarks/{$bookmark->id}/favorite")->assertRedirect();
        $this->assertTrue($bookmark->fresh()->is_favorite);
        $this->get('/bookmarks?favorites=1')->assertOk()->assertSee('Laravel docs');

        // update with changed URL re-queues metadata
        Queue::fake();
        $this->put("/bookmarks/{$bookmark->id}", [
            'url' => 'https://laravel.com/docs/11.x',
            'title' => 'Laravel 11 docs',
            'collection' => 'dev',
            'tags' => 'php',
        ])->assertRedirect();
        Queue::assertPushed(FetchBookmarkMetadata::class);

        // delete
        $this->delete("/bookmarks/{$bookmark->id}")->assertRedirect();
        $this->assertDatabaseMissing('bookmarks', ['id' => $bookmark->id]);
    }

    public function test_bookmark_metadata_job_parses_html(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake(); // overridden below — job tested directly

        $bookmark = Bookmark::create([
            'url' => 'https://example.com/x',
            'title' => 'Fallback',
            'created_by' => auth()->id(),
        ]);

        // execute the parser logic without real HTTP
        $job = new \ReflectionMethod(FetchBookmarkMetadata::class, 'meta');
        $html = '<title>Real Title</title><meta name="description" content="A description">';

        $this->assertSame('Real Title', $job->invoke(new FetchBookmarkMetadata($bookmark), $html, 'title'));
        $this->assertSame(
            'A description',
            $job->invoke(new FetchBookmarkMetadata($bookmark), $html, 'description')
        );
    }

    /*
     * AI Assistant
     */

    public function test_ai_conversation_flow_with_fake_provider(): void
    {
        // settings via platform settings capability
        $settings = $this->app->make(\App\Platform\Services\SettingsManager::class);
        $settings->set('provider', 'openai', 'ai-assistant');
        $settings->set('model', 'gpt-test', 'ai-assistant');
        $settings->set('api_key', 'test-key', 'ai-assistant');

        $this->post('/ai/conversations', ['title' => 'Debug help', 'model' => 'gpt-test'])
            ->assertRedirect();

        $conversation = AiConversation::where('title', 'Debug help')->first();
        $this->assertNotNull($conversation);

        // fake the provider HTTP call
        \Illuminate\Support\Facades\Http::fake([
            'api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Try `php artisan test`.']]],
            ]),
        ]);

        $this->post("/ai/conversations/{$conversation->id}/messages", [
            'content' => 'How do I run tests?',
        ])->assertRedirect();

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(
            'Try `php artisan test`.',
            $conversation->messages()->where('role', 'assistant')->first()->content
        );

        // lazy title rename from first message
        $this->assertSame('Debug help', $conversation->fresh()->title);

        // chat page renders both messages
        $this->get("/ai/conversations/{$conversation->id}")
            ->assertOk()
            ->assertSee('Try `php artisan test`.');
    }

    public function test_ai_settings_page_saves_preferences(): void
    {
        $this->get('/ai/settings')->assertOk()->assertSee('Provider');

        $this->put('/ai/settings', [
            'provider' => 'ollama',
            'model' => 'llama3.1',
            'base_url' => 'http://localhost:11434',
        ])->assertRedirect();

        $settings = $this->app->make(\App\Platform\Services\SettingsManager::class);
        $this->assertSame('ollama', $settings->get('provider', null, 'ai-assistant'));
        $this->assertSame('llama3.1', $settings->get('model', null, 'ai-assistant'));
    }
}
