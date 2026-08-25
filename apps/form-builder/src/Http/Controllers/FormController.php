<?php

namespace PlatformApps\FormBuilder\Http\Controllers;

use Illuminate\Routing\Controller;
use PlatformApps\FormBuilder\Http\Requests\StoreFormRequest;
use PlatformApps\FormBuilder\Models\Field;
use PlatformApps\FormBuilder\Models\Form;

class FormController extends Controller
{
    public function index()
    {
        return view('form-builder::index', [
            'forms' => Form::withCount(['fields', 'submissions'])->latest('id')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('form-builder::form', ['form' => new Form(['is_published' => false])]);
    }

    public function store(StoreFormRequest $request)
    {
        $form = Form::create($request->validated() + [
            'slug' => Form::uniqueSlug($request->string('title')),
            'is_published' => $request->boolean('is_published'),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('form-builder.build', $form)
            ->with('status', "Form [{$form->title}] created. Add its fields below.");
    }

    public function edit(Form $form)
    {
        return view('form-builder::form', ['form' => $form]);
    }

    public function update(StoreFormRequest $request, Form $form)
    {
        // The slug is the shared link. It is derived from the title once, at
        // creation, and then left alone so renaming a form never breaks a
        // link somebody already handed out.
        $form->update($request->validated() + [
            'is_published' => $request->boolean('is_published'),
        ]);

        return redirect()->route('form-builder.index')->with('status', 'Form updated.');
    }

    public function destroy(Form $form)
    {
        $title = $form->title;

        // Fields and submissions go with it — the foreign keys cascade.
        $form->delete();

        return redirect()->route('form-builder.index')->with('status', "Form [{$title}] deleted.");
    }

    /**
     * The field editor: the form's own structure, edited field by field.
     */
    public function build(Form $form)
    {
        return view('form-builder::build', [
            'form' => $form->load('fields'),
            'types' => Field::TYPES,
            'choiceTypes' => Field::CHOICE_TYPES,
        ]);
    }
}
