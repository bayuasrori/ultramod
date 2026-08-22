<?php

namespace PlatformApps\Calendar\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use PlatformApps\Calendar\Http\Requests\StoreEventRequest;
use PlatformApps\Calendar\Models\CalendarEvent;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')));

        $start = $month->copy()->startOfMonth()->startOfWeek();
        $end = $month->copy()->endOfMonth()->endOfWeek();

        $events = CalendarEvent::whereBetween('starts_at', [$start, $end->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn ($event) => $event->starts_at->format('Y-m-d'));

        // 6x7 grid of days
        $weeks = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $cursor->copy();
                $week[] = [
                    'date' => $day,
                    'inMonth' => $day->isSameMonth($month),
                    'events' => $events->get($day->format('Y-m-d'), collect()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('calendar::index', [
            'month' => $month,
            'weeks' => $weeks,
            'upcoming' => CalendarEvent::where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('calendar::events.form', [
            'event' => new CalendarEvent(),
            'defaultDate' => $request->input('date', now()->format('Y-m-d')),
        ]);
    }

    public function store(StoreEventRequest $request, AuditLogger $audit)
    {
        $event = CalendarEvent::create([
            ...$request->validated(),
            'all_day' => $request->boolean('all_day'),
            'created_by' => auth()->id(),
        ]);

        $audit->log('calendar.event.created', target: $event);

        return redirect()->route('calendar.index', ['month' => $event->starts_at->format('Y-m')]);
    }

    public function edit(CalendarEvent $event)
    {
        return view('calendar::events.form', [
            'event' => $event,
            'defaultDate' => $event->starts_at->format('Y-m-d'),
        ]);
    }

    public function update(StoreEventRequest $request, CalendarEvent $event, AuditLogger $audit)
    {
        $event->update([
            ...$request->validated(),
            'all_day' => $request->boolean('all_day'),
        ]);

        $audit->log('calendar.event.updated', target: $event);

        return redirect()->route('calendar.index', ['month' => $event->starts_at->format('Y-m')]);
    }

    public function destroy(CalendarEvent $event, AuditLogger $audit)
    {
        $audit->log('calendar.event.deleted', metadata: ['title' => $event->title]);
        $event->delete();

        return redirect()->route('calendar.index');
    }
}
