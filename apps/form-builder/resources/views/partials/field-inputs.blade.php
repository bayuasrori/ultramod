{{-- Shared by the "add field" form and every inline field editor. Only the
     add form replays old input: a failed edit of one field must not prefill
     the editors of all the others. --}}
@php($replay = $field === null)
<div data-field-form>
    <div class="mb-2">
        <label class="form-label">Label</label>
        <input type="text" name="label" required class="form-control form-control-sm @error('label') is-invalid @enderror"
               value="{{ $replay ? old('label') : $field->label }}">
        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-2">
        <label class="form-label">Type</label>
        <select name="type" class="form-select form-select-sm" data-field-type>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(($replay ? old('type', 'text') : $field->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-2" data-field-options>
        <label class="form-label">Options</label>
        <textarea name="options" rows="3" class="form-control form-control-sm @error('options') is-invalid @enderror"
                  placeholder="One choice per line">{{ $replay ? old('options') : $field->options }}</textarea>
        @error('options')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-2">
        <label class="form-label">Placeholder</label>
        <input type="text" name="placeholder" class="form-control form-control-sm"
               value="{{ $replay ? old('placeholder') : $field->placeholder }}">
    </div>

    <div class="mb-2">
        <label class="form-label">Help text</label>
        <input type="text" name="help" class="form-control form-control-sm"
               value="{{ $replay ? old('help') : $field->help }}">
    </div>

    <div class="form-check mb-3">
        <input type="hidden" name="is_required" value="0">
        <input type="checkbox" name="is_required" value="1" class="form-check-input"
               id="required-{{ $field?->id ?? 'new' }}" @checked($replay ? old('is_required') : $field->is_required)>
        <label class="form-check-label" for="required-{{ $field?->id ?? 'new' }}">Required</label>
    </div>
</div>
