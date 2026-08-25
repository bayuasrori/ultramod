<?php

namespace PlatformApps\FormBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PlatformApps\FormBuilder\Http\Requests\StoreFieldRequest;
use PlatformApps\FormBuilder\Models\Field;
use PlatformApps\FormBuilder\Models\Form;

class FieldController extends Controller
{
    public function store(StoreFieldRequest $request, Form $form)
    {
        $form->fields()->create($this->attributes($request, $form));

        return redirect()->route('form-builder.build', $form)->with('status', 'Field added.');
    }

    public function update(StoreFieldRequest $request, Form $form, Field $field)
    {
        $this->authorizeField($form, $field);

        // The key is what every stored answer is filed under, so it is fixed
        // at creation. Renaming a label must not orphan answers already
        // collected, so the key is never recomputed on edit.
        $attributes = $this->attributes($request, $form, $field);
        unset($attributes['key']);

        $field->update($attributes);

        return redirect()->route('form-builder.build', $form)->with('status', 'Field updated.');
    }

    public function destroy(Form $form, Field $field)
    {
        $this->authorizeField($form, $field);

        $field->delete();

        return redirect()->route('form-builder.build', $form)->with('status', 'Field removed.');
    }

    /**
     * Swap a field with its neighbour in the given direction.
     */
    public function move(Request $request, Form $form, Field $field)
    {
        $this->authorizeField($form, $field);

        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $neighbour = $form->fields()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('position', '<', $field->position)->orderByDesc('position'),
                fn ($q) => $q->where('position', '>', $field->position)->orderBy('position'),
            )
            ->first();

        if ($neighbour !== null) {
            [$field->position, $neighbour->position] = [$neighbour->position, $field->position];
            $field->save();
            $neighbour->save();
        }

        return redirect()->route('form-builder.build', $form);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(StoreFieldRequest $request, Form $form, ?Field $field = null): array
    {
        $validated = $request->validated();
        $isChoice = in_array($validated['type'], Field::CHOICE_TYPES, true);

        return [
            'label' => $validated['label'],
            'key' => $form->uniqueFieldKey($validated['label'], $field?->id),
            'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null,
            'help' => $validated['help'] ?? null,
            'options' => $isChoice ? ($validated['options'] ?? null) : null,
            'is_required' => $request->boolean('is_required'),
            'position' => $field?->position ?? $form->nextFieldPosition(),
        ];
    }

    /**
     * Route model binding resolves the field on its own, so it has to be
     * checked against the form in the URL.
     */
    protected function authorizeField(Form $form, Field $field): void
    {
        abort_unless($field->form_id === $form->id, 404);
    }
}
