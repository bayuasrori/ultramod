<?php

namespace PlatformApps\FormBuilder\Http\Controllers;

use Illuminate\Routing\Controller;
use PlatformApps\FormBuilder\Models\Form;
use PlatformApps\FormBuilder\Models\Submission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Form $form)
    {
        return view('form-builder::submissions', [
            'form' => $form->load('fields'),
            'submissions' => $form->submissions()->with('submitter')->paginate(25),
        ]);
    }

    /**
     * One row per submission, one column per field as the form stands today.
     */
    public function export(Form $form): StreamedResponse
    {
        $form->load('fields');

        $filename = $form->slug.'-submissions.csv';

        return response()->streamDownload(function () use ($form) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_merge(
                ['id', 'submitted_at', 'submitted_by'],
                $form->fields->pluck('label')->all(),
            ));

            $form->submissions()->with('submitter')->chunk(200, function ($submissions) use ($handle, $form) {
                foreach ($submissions as $submission) {
                    fputcsv($handle, array_merge(
                        [
                            $submission->id,
                            $submission->created_at?->toDateTimeString(),
                            $submission->submitter?->name ?? 'anonymous',
                        ],
                        $form->fields->map(fn ($field) => $submission->answerFor($field, ''))->all(),
                    ));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Form $form, Submission $submission)
    {
        abort_unless($submission->form_id === $form->id, 404);

        $submission->delete();

        return redirect()->route('form-builder.submissions', $form)->with('status', 'Submission deleted.');
    }
}
