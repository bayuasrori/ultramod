@extends('platform.layout')

@section('title', 'Responses — '.$form->title)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Responses</h1>
            <div class="small text-muted">{{ $form->title }}</div>
        </div>
        <div class="d-flex gap-2">
            @if ($submissions->total() > 0)
                <a href="{{ route('form-builder.submissions.export', $form) }}" class="btn btn-outline-primary">Export CSV</a>
            @endif
            @can('form-builder.update')
                <a href="{{ route('form-builder.build', $form) }}" class="btn btn-outline-secondary">Back to builder</a>
            @endcan
        </div>
    </div>

    @if ($submissions->isEmpty())
        <div class="alert alert-light text-center">No responses yet.</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>By</th>
                            @foreach ($form->fields as $field)
                                <th>{{ $field->label }}</th>
                            @endforeach
                            @can('form-builder.delete')<th class="text-end">Actions</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($submissions as $submission)
                        <tr>
                            <td class="text-nowrap small">{{ $submission->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="small">{{ $submission->submitter?->name ?? 'anonymous' }}</td>
                            @foreach ($form->fields as $field)
                                <td>{{ $submission->answerFor($field) }}</td>
                            @endforeach
                            @can('form-builder.delete')
                                <td class="text-end">
                                    <form method="POST" action="{{ route('form-builder.submissions.destroy', [$form, $submission]) }}"
                                          onsubmit="return confirm('Delete this response?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $submissions->links() }}</div>
    @endif
@endsection
