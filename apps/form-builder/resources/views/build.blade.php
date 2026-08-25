@extends('platform.layout')

@section('title', 'Build '.$form->title)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">{{ $form->title }}</h1>
            <div class="small text-muted">
                <code>/form-builder/f/{{ $form->slug }}</code>
                @if ($form->is_published)
                    <span class="badge text-bg-success">published</span>
                @else
                    <span class="badge text-bg-secondary">draft</span>
                @endif
                @if ($form->is_public)
                    <span class="badge text-bg-info">public</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            @if ($form->is_published)
                @can('form-builder.submit')
                    <a href="{{ route('form-builder.fill', $form->slug) }}" class="btn btn-outline-success">Preview</a>
                @endcan
            @endif
            <a href="{{ route('form-builder.submissions', $form) }}" class="btn btn-outline-primary">Responses</a>
            <a href="{{ route('form-builder.edit', $form) }}" class="btn btn-outline-secondary">Settings</a>
        </div>
    </div>

    @if ($form->is_public && $form->is_published)
        <div class="alert alert-info py-2 mb-3">
            <strong>Public link:</strong>
            <code>{{ url('/form-builder/f/'.$form->slug) }}</code>
            — anyone can fill this form without logging in.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            @if ($form->fields->isEmpty())
                <div class="alert alert-light text-center">No fields yet. Add the first one on the right.</div>
            @endif

            @foreach ($form->fields as $field)
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $field->label }}</strong>
                                @if ($field->is_required)<span class="badge text-bg-warning">required</span>@endif
                                <div class="small text-muted">
                                    <span class="badge text-bg-light border">{{ $field->typeLabel() }}</span>
                                    <code>{{ $field->key }}</code>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                @foreach (['up' => '&uarr;', 'down' => '&darr;'] as $direction => $arrow)
                                    <form method="POST" action="{{ route('form-builder.fields.move', [$form, $field]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{!! $arrow !!}</button>
                                    </form>
                                @endforeach
                                <button class="btn btn-sm btn-outline-primary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#field-{{ $field->id }}">Edit</button>
                                <form method="POST" action="{{ route('form-builder.fields.destroy', [$form, $field]) }}"
                                      onsubmit="return confirm('Remove the field {{ $field->label }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                </form>
                            </div>
                        </div>

                        <div class="collapse mt-3" id="field-{{ $field->id }}">
                            <form method="POST" action="{{ route('form-builder.fields.update', [$form, $field]) }}">
                                @csrf
                                @method('PUT')
                                @include('form-builder::partials.field-inputs', ['field' => $field, 'types' => $types, 'choiceTypes' => $choiceTypes])
                                <button type="submit" class="btn btn-sm btn-primary">Save field</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Add a field</h2>
                    <form method="POST" action="{{ route('form-builder.fields.store', $form) }}">
                        @csrf
                        @include('form-builder::partials.field-inputs', ['field' => null, 'types' => $types, 'choiceTypes' => $choiceTypes])
                        <button type="submit" class="btn btn-primary">Add field</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var choiceTypes = @json($choiceTypes);

    // A dropdown or single-choice field is the only one that needs options,
    // so the textarea follows the type select.
    document.querySelectorAll('[data-field-form]').forEach(function (wrapper) {
        var select = wrapper.querySelector('[data-field-type]');
        var options = wrapper.querySelector('[data-field-options]');

        function sync() {
            options.style.display = choiceTypes.indexOf(select.value) === -1 ? 'none' : '';
        }

        select.addEventListener('change', sync);
        sync();
    });
})();
</script>
@endpush
