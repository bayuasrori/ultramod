@extends('platform.layout')

@section('title', $form->title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-1">{{ $form->title }}</h1>
                    @if ($form->description)
                        <p class="text-muted">{{ $form->description }}</p>
                    @endif
                    @if ($public ?? false)
                        <div class="small text-muted mb-3">This form is public — no login required.</div>
                    @endif

                    @if ($form->fields->isEmpty())
                        <div class="alert alert-light mb-0">This form has no fields yet.</div>
                    @else
                        <form method="POST" action="{{ route('form-builder.fill.store', $form->slug) }}">
                            @csrf

                            @foreach ($form->fields as $field)
                                @php($value = old($field->key))

                                @if ($field->type === 'checkbox')
                                    <div class="form-check mb-3">
                                        <input type="hidden" name="{{ $field->key }}" value="0">
                                        <input type="checkbox" id="{{ $field->key }}" name="{{ $field->key }}" value="1"
                                               class="form-check-input @error($field->key) is-invalid @enderror" @checked($value)>
                                        <label class="form-check-label" for="{{ $field->key }}">
                                            {{ $field->label }}@if ($field->is_required)<span class="text-danger">*</span>@endif
                                        </label>
                                        @error($field->key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if ($field->help)<div class="form-text">{{ $field->help }}</div>@endif
                                    </div>
                                @else
                                    <div class="mb-3">
                                        <label for="{{ $field->key }}" class="form-label">
                                            {{ $field->label }}@if ($field->is_required)<span class="text-danger">*</span>@endif
                                        </label>

                                        @switch($field->type)
                                            @case('textarea')
                                                <textarea id="{{ $field->key }}" name="{{ $field->key }}" rows="4"
                                                          placeholder="{{ $field->placeholder }}"
                                                          class="form-control @error($field->key) is-invalid @enderror">{{ $value }}</textarea>
                                                @break

                                            @case('select')
                                                <select id="{{ $field->key }}" name="{{ $field->key }}"
                                                        class="form-select @error($field->key) is-invalid @enderror">
                                                    <option value="">Choose…</option>
                                                    @foreach ($field->choices() as $choice)
                                                        <option value="{{ $choice }}" @selected($value === $choice)>{{ $choice }}</option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case('radio')
                                                @foreach ($field->choices() as $i => $choice)
                                                    <div class="form-check">
                                                        <input type="radio" class="form-check-input @error($field->key) is-invalid @enderror"
                                                               id="{{ $field->key }}-{{ $i }}" name="{{ $field->key }}"
                                                               value="{{ $choice }}" @checked($value === $choice)>
                                                        <label class="form-check-label" for="{{ $field->key }}-{{ $i }}">{{ $choice }}</label>
                                                    </div>
                                                @endforeach
                                                @break

                                            @default
                                                <input type="{{ ['number' => 'number', 'email' => 'email', 'date' => 'date'][$field->type] ?? 'text' }}"
                                                       id="{{ $field->key }}" name="{{ $field->key }}"
                                                       placeholder="{{ $field->placeholder }}"
                                                       class="form-control @error($field->key) is-invalid @enderror"
                                                       value="{{ $value }}">
                                        @endswitch

                                        @error($field->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        @if ($field->help)<div class="form-text">{{ $field->help }}</div>@endif
                                    </div>
                                @endif
                            @endforeach

                            {{-- Extension point: another app can append to any published form. --}}
                            @extensionslot('form-builder.form.footer', ['form' => $form])

                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
