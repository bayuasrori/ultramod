@extends('platform.layout')

@section('title', 'Forms')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Forms</h1>
        @can('form-builder.create')
            <a href="{{ route('form-builder.create') }}" class="btn btn-primary">+ New form</a>
        @endcan
    </div>

    @if ($forms->isEmpty())
        <div class="alert alert-light text-center">
            No forms yet.
            @can('form-builder.create')<a href="{{ route('form-builder.create') }}">Build your first one</a>.@endcan
        </div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Form</th>
                            <th>Fields</th>
                            <th>Responses</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($forms as $form)
                        <tr>
                            <td>
                                <strong>{{ $form->title }}</strong>
                                <div class="small text-muted"><code>/form-builder/f/{{ $form->slug }}</code></div>
                            </td>
                            <td>{{ $form->fields_count }}</td>
                            <td>
                                <a href="{{ route('form-builder.submissions', $form) }}">{{ $form->submissions_count }}</a>
                            </td>
                            <td>
                                @if ($form->is_published)
                                    <span class="badge text-bg-success">published</span>
                                @else
                                    <span class="badge text-bg-secondary">draft</span>
                                @endif
                                @if ($form->is_public)
                                    <span class="badge text-bg-info">public</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($form->is_published)
                                    @can('form-builder.submit')
                                        <a href="{{ route('form-builder.fill', $form->slug) }}" class="btn btn-sm btn-outline-success">Open</a>
                                    @endcan
                                @endif
                                @can('form-builder.update')
                                    <a href="{{ route('form-builder.build', $form) }}" class="btn btn-sm btn-primary">Build</a>
                                    <a href="{{ route('form-builder.edit', $form) }}" class="btn btn-sm btn-outline-primary">Settings</a>
                                @endcan
                                @can('form-builder.delete')
                                    <form method="POST" action="{{ route('form-builder.destroy', $form) }}" class="d-inline"
                                          onsubmit="return confirm('Delete {{ $form->title }} with all its fields and responses?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $forms->links() }}</div>
    @endif
@endsection
