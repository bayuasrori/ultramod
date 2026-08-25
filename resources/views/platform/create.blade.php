@extends('platform.layout')

@section('title', 'New App')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">Create a new app</h1>
                    <p class="text-muted">
                        Scaffolds a working app under <code>apps/&lt;id&gt;/</code> — ready to be filled in,
                        or generated with a complete CRUD stack from your table definition.
                    </p>

                    <form method="POST" action="{{ route('platform.apps.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">App name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g. weather" value="{{ old('name') }}" autofocus required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Lowercase letters, numbers and dashes. Becomes the app ID, URL prefix and namespace.
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input type="checkbox" id="crud" name="crud" value="1" class="form-check-input"
                                   {{ old('crud') ? 'checked' : '' }}>
                            <label class="form-check-label" for="crud">
                                Generate CRUD (model, migration, controller, form request, views, resource routes)
                            </label>
                        </div>

                        <div id="crud-options" class="border rounded p-3 mb-4 bg-body-tertiary" style="display: none">
                            <div class="mb-3">
                                <label for="table" class="form-label">Table name</label>
                                <input type="text" id="table" name="table" class="form-control @error('table') is-invalid @enderror"
                                       placeholder="e.g. products" value="{{ old('table') }}" autocomplete="off">
                                @error('table')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Plural, lowercase. Model name derived automatically.</div>
                            </div>

                            <label class="form-label mb-2">Columns</label>

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-body-secondary" style="min-width: 8rem"><code>id</code></span>
                                <input type="text" class="form-control" value="big integer · primary key · auto increment" disabled>
                            </div>

                            <div id="columns"></div>

                            @error('columns')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                            <button type="button" id="add-column" class="btn btn-sm btn-outline-secondary mt-2">+ Add column</button>
                            <div class="form-text">
                                <code>id</code>, <code>created_at</code> and <code>updated_at</code> are always generated — do not add them here.
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input type="checkbox" id="activate" name="activate" value="1" class="form-check-input" checked>
                            <label class="form-check-label" for="activate">Install &amp; enable immediately</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Scaffold app</button>
                            <a href="{{ route('platform.apps.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <template id="column-row">
        <div class="input-group input-group-sm mb-2 column-row">
            <input type="text" name="columns[name][]" class="form-control" placeholder="column_name" autocomplete="off">
            <select name="columns[type][]" class="form-select" style="max-width: 14rem">
                <option value="string">string — varchar(255)</option>
                <option value="text">text — long text</option>
                <option value="integer">integer — int</option>
                <option value="float">float</option>
                <option value="decimal">decimal — 10,2</option>
                <option value="boolean">boolean</option>
                <option value="date">date</option>
                <option value="datetime">datetime</option>
            </select>
            <button type="button" class="btn btn-outline-danger remove-column">&times;</button>
        </div>
    </template>

    @push('scripts')
        @php
            $oldColumns = [];
            foreach (old('columns.name', []) as $i => $oldName) {
                $oldColumns[] = ['name' => $oldName, 'type' => old('columns.type.'.$i, 'string')];
            }
        @endphp
        <script>
            (function () {
                var toggle = document.getElementById('crud');
                var options = document.getElementById('crud-options');
                var columns = document.getElementById('columns');
                var template = document.getElementById('column-row');

                function sync() {
                    options.style.display = toggle.checked ? '' : 'none';
                }

                function addRow(name, type) {
                    columns.appendChild(template.content.cloneNode(true));

                    var row = columns.lastElementChild;
                    row.querySelector('input').value = name || '';
                    row.querySelector('select').value = type || 'string';
                }

                toggle.addEventListener('change', sync);
                document.getElementById('add-column').addEventListener('click', function () {
                    addRow();
                });
                columns.addEventListener('click', function (e) {
                    if (e.target.classList.contains('remove-column')) {
                        e.target.closest('.column-row').remove();
                    }
                });

                // Restore what was typed when the form comes back with errors,
                // otherwise start with a single empty row.
                var submitted = @json($oldColumns);

                if (submitted.length) {
                    submitted.forEach(function (column) {
                        addRow(column.name, column.type);
                    });
                } else {
                    addRow();
                }

                sync();
            })();
        </script>
    @endpush
@endsection
