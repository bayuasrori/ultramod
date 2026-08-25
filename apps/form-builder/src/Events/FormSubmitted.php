<?php

namespace PlatformApps\FormBuilder\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PlatformApps\FormBuilder\Models\Form;
use PlatformApps\FormBuilder\Models\Submission;

/**
 * Extension point: other apps can react to a submission (notify, score,
 * forward to a queue) without Form Builder knowing they exist.
 */
class FormSubmitted
{
    use Dispatchable;

    public function __construct(public Form $form, public Submission $submission) {}
}
