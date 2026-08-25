<?php

namespace PlatformApps\FormBuilder\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PlatformApps\FormBuilder\Events\FormSubmitted;
use PlatformApps\FormBuilder\Models\Form;

class FillController extends Controller
{
    /**
     * Render a published form from its own field definitions.
     * Public forms are accessible without login; private forms require
     * the form-builder.submit permission.
     */
    public function show(string $slug)
    {
        $form = $this->resolveForm($slug);

        return view('form-builder::fill', ['form' => $form, 'public' => $form->is_public]);
    }

    public function store(Request $request, string $slug, AuditLogger $audit)
    {
        $form = $this->resolveForm($slug);

        abort_if($form->fields->isEmpty(), 404);

        // An unticked checkbox submits nothing, so it has to be filled in
        // before validation or "required" could never fail.
        $request->merge($this->checkboxDefaults($form, $request));

        $validated = $request->validate(
            $this->rules($form),
            [],
            $form->validationAttributes(),
        );

        // Validation only returns the keys that were submitted. Storing every
        // field of the form, blank ones included, keeps a submission shaped
        // like the form it answered — which is what the table and the CSV
        // export line their columns up against.
        $answers = [];

        foreach ($form->fields as $field) {
            $answers[$field->key] = $validated[$field->key] ?? null;
        }

        $submission = $form->submissions()->create([
            'submitted_by' => auth()->id(),
            'answers' => $answers,
        ]);

        $audit->log('form-builder.submitted', $submission, [
            'form' => $form->slug,
            'fields' => array_keys($answers),
        ]);

        FormSubmitted::dispatch($form, $submission);

        return redirect()
            ->route('form-builder.fill', $form->slug)
            ->with('status', $form->success_message ?: 'Thanks — your response was recorded.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(Form $form): array
    {
        $rules = $form->validationRules();

        foreach ($form->fields as $field) {
            if ($field->requiredCheckbox()) {
                $rules[$field->key] = ['accepted'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, int>
     */
    protected function checkboxDefaults(Form $form, Request $request): array
    {
        $defaults = [];

        foreach ($form->fields as $field) {
            if ($field->type === 'checkbox' && ! $request->has($field->key)) {
                $defaults[$field->key] = 0;
            }
        }

        return $defaults;
    }

    /**
     * Resolve a published form by slug. Public forms are accessible to
     * anyone; private forms require authentication and the submit permission.
     */
    protected function resolveForm(string $slug): Form
    {
        $form = Form::with('fields')->where('slug', $slug)->where('is_published', true)->firstOrFail();

        if (! $form->is_public) {
            abort_unless(auth()->check(), 403);
            abort_unless(auth()->user()->can('form-builder.submit'), 403);
        }

        return $form;
    }
}
