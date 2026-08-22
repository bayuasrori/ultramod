@php($current = $assignment?->status)
<div class="d-flex align-items-center gap-1 flex-wrap mb-1">
    <span class="small text-muted">Status:</span>
    @if ($current)
        <span class="badge text-bg-{{ $current->color }}">{{ $current->name }}</span>
    @else
        <span class="badge text-bg-light border">none</span>
    @endif
    @can('notes-status.update')
        <form method="POST" action="{{ route('notes-status.assign', $note->id) }}" class="d-inline-flex gap-1">
            @csrf
            <select name="note_status_id" class="form-select form-select-sm" style="width: auto;"
                    onchange="this.form.submit()">
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected($current?->id === $status->id)>
                        {{ $status->name }}
                    </option>
                @endforeach
            </select>
        </form>
    @endcan
</div>
