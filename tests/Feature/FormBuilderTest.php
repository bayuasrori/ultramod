<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use PlatformApps\FormBuilder\Events\FormSubmitted;
use PlatformApps\FormBuilder\Models\Field;
use PlatformApps\FormBuilder\Models\Form;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);

        // The member role has to exist before the app is installed: that is
        // when the platform grants its view permissions.
        $this->seed(PlatformRoleSeeder::class);

        Artisan::call('platform:app:discover');
        Artisan::call('platform:app:install', ['app' => 'form-builder']);
        Artisan::call('platform:app:enable', ['app' => 'form-builder']);

        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    /**
     * A form with one field of each interesting kind, published and ready
     * to be filled in.
     */
    protected function surveyForm(): Form
    {
        $form = Form::create([
            'title' => 'Customer survey',
            'slug' => Form::uniqueSlug('Customer survey'),
            'success_message' => 'Thanks for the feedback!',
            'is_published' => true,
        ]);

        $form->fields()->createMany([
            ['label' => 'Your name', 'key' => 'your_name', 'type' => 'text', 'is_required' => true, 'position' => 1],
            ['label' => 'Email', 'key' => 'email', 'type' => 'email', 'is_required' => false, 'position' => 2],
            ['label' => 'Plan', 'key' => 'plan', 'type' => 'select', 'options' => "Free\nPro\nEnterprise", 'is_required' => true, 'position' => 3],
            ['label' => 'Accept terms', 'key' => 'accept_terms', 'type' => 'checkbox', 'is_required' => true, 'position' => 4],
        ]);

        return $form->fresh('fields');
    }

    // ------------------------------------------------------------- building

    public function test_a_form_is_created_and_lands_in_the_field_builder(): void
    {
        $this->post('/form-builder', [
            'title' => 'Customer survey',
            'description' => 'Tell us how we are doing',
            'is_published' => '1',
        ])->assertRedirect();

        $form = Form::firstOrFail();

        $this->assertSame('customer-survey', $form->slug);
        $this->assertTrue($form->is_published);

        $this->get("/form-builder/{$form->id}/build")->assertOk()->assertSee('Add a field');
    }

    public function test_fields_are_added_with_a_derived_key_and_appended_in_order(): void
    {
        $form = Form::create(['title' => 'Survey', 'slug' => 'survey']);

        $this->post("/form-builder/{$form->id}/fields", [
            'label' => 'Your name', 'type' => 'text', 'is_required' => '1',
        ])->assertRedirect();

        $this->post("/form-builder/{$form->id}/fields", [
            'label' => 'Your name', 'type' => 'text',
        ])->assertRedirect();

        $keys = $form->fields()->pluck('key')->all();

        $this->assertSame(['your_name', 'your_name_2'], $keys, 'keys must be unique within a form');
        $this->assertSame([1, 2], $form->fields()->pluck('position')->all());
    }

    public function test_a_choice_field_needs_at_least_two_options(): void
    {
        $form = Form::create(['title' => 'Survey', 'slug' => 'survey']);

        $this->post("/form-builder/{$form->id}/fields", [
            'label' => 'Plan', 'type' => 'select', 'options' => 'Free',
        ])->assertSessionHasErrors('options');

        $this->assertSame(0, $form->fields()->count());
    }

    public function test_renaming_a_field_keeps_its_key_so_existing_answers_survive(): void
    {
        $form = $this->surveyForm();
        $field = $form->fields()->where('key', 'your_name')->firstOrFail();

        $this->put("/form-builder/{$form->id}/fields/{$field->id}", [
            'label' => 'Full name', 'type' => 'text', 'is_required' => '1',
        ])->assertRedirect();

        $field->refresh();

        $this->assertSame('Full name', $field->label);
        $this->assertSame('your_name', $field->key, 'the storage key must not move under existing answers');
    }

    public function test_fields_can_be_reordered(): void
    {
        $form = $this->surveyForm();
        $second = $form->fields->firstWhere('key', 'email');

        $this->post("/form-builder/{$form->id}/fields/{$second->id}/move", ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            ['email', 'your_name', 'plan', 'accept_terms'],
            $form->fresh()->fields()->pluck('key')->all(),
        );
    }

    public function test_a_field_of_another_form_cannot_be_edited_through_this_one(): void
    {
        $mine = $this->surveyForm();
        $other = Form::create(['title' => 'Other', 'slug' => 'other']);
        $field = $mine->fields()->first();

        $this->delete("/form-builder/{$other->id}/fields/{$field->id}")->assertNotFound();
        $this->assertModelExists($field);
    }

    // -------------------------------------------------------------- filling

    public function test_an_unpublished_form_cannot_be_opened(): void
    {
        $form = $this->surveyForm();
        $form->update(['is_published' => false]);

        $this->get("/form-builder/f/{$form->slug}")->assertNotFound();
    }

    public function test_a_published_form_renders_every_field(): void
    {
        $form = $this->surveyForm();

        $this->get("/form-builder/f/{$form->slug}")
            ->assertOk()
            ->assertSee('Your name')
            ->assertSee('Enterprise')
            ->assertSee('Accept terms');
    }

    public function test_submissions_are_validated_from_the_field_definitions(): void
    {
        $form = $this->surveyForm();

        // required text missing, email malformed, plan outside the choices,
        // required checkbox not ticked
        $this->post("/form-builder/f/{$form->slug}", [
            'email' => 'not-an-email',
            'plan' => 'Platinum',
        ])->assertSessionHasErrors(['your_name', 'email', 'plan', 'accept_terms']);

        $this->assertSame(0, $form->submissions()->count());
    }

    public function test_a_valid_submission_is_stored_keyed_by_field_and_announced(): void
    {
        Event::fake([FormSubmitted::class]);

        $form = $this->surveyForm();

        $this->post("/form-builder/f/{$form->slug}", [
            'your_name' => 'Rina',
            'email' => 'rina@example.com',
            'plan' => 'Pro',
            'accept_terms' => '1',
        ])->assertRedirect("/form-builder/f/{$form->slug}")
            ->assertSessionHas('status', 'Thanks for the feedback!');

        $submission = $form->submissions()->firstOrFail();

        $this->assertSame('Rina', $submission->answers['your_name']);
        $this->assertSame('Pro', $submission->answers['plan']);
        $this->assertSame(auth()->id(), $submission->submitted_by);

        Event::assertDispatched(FormSubmitted::class, fn ($e) => $e->form->is($form));

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'form-builder.submitted']);
    }

    public function test_optional_fields_may_be_left_blank(): void
    {
        $form = $this->surveyForm();

        $this->post("/form-builder/f/{$form->slug}", [
            'your_name' => 'Budi',
            'plan' => 'Free',
            'accept_terms' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($form->submissions()->firstOrFail()->answers['email']);
    }

    // ---------------------------------------------------------- submissions

    public function test_responses_are_listed_and_exported_as_csv(): void
    {
        $form = $this->surveyForm();
        $this->post("/form-builder/f/{$form->slug}", [
            'your_name' => 'Rina', 'email' => 'rina@example.com', 'plan' => 'Pro', 'accept_terms' => '1',
        ]);

        $this->get("/form-builder/{$form->id}/submissions")
            ->assertOk()
            ->assertSee('Rina')
            ->assertSee('Pro');

        $csv = $this->get("/form-builder/{$form->id}/submissions/export")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->streamedContent();

        $this->assertStringContainsString('Your name', $csv);
        $this->assertStringContainsString('Rina', $csv);
        $this->assertStringContainsString('Yes', $csv, 'a ticked checkbox exports as Yes');
    }

    public function test_deleting_a_form_takes_its_fields_and_responses_with_it(): void
    {
        $form = $this->surveyForm();
        $this->post("/form-builder/f/{$form->slug}", [
            'your_name' => 'Rina', 'plan' => 'Pro', 'accept_terms' => '1',
        ]);

        $this->delete("/form-builder/{$form->id}")->assertRedirect('/form-builder');

        $this->assertDatabaseCount('form_builder_forms', 0);
        $this->assertDatabaseCount('form_builder_fields', 0);
        $this->assertDatabaseCount('form_builder_submissions', 0);
    }

    // ---------------------------------------------------------- permissions

    public function test_submitting_is_a_permission_of_its_own(): void
    {
        $form = $this->surveyForm();

        $role = PlatformRole::where('slug', 'member')->firstOrFail();

        $member = User::create([
            'name' => 'Plain Member',
            'email' => 'member@example.com',
            'password' => 'secret123',
            'platform_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($member);

        // Installing the app grants members the view permission only.
        $this->get('/form-builder')->assertOk();
        $this->get("/form-builder/{$form->id}/build")->assertForbidden();
        $this->get("/form-builder/f/{$form->slug}")->assertForbidden();

        $role->permissions()->syncWithoutDetaching(
            PlatformAppPermission::where('app_id', 'form-builder')->where('name', 'form-builder.submit')->pluck('id'),
        );

        $this->actingAs($member->fresh());

        $this->get("/form-builder/f/{$form->slug}")->assertOk();
        $this->get("/form-builder/{$form->id}/build")->assertForbidden();
    }

    public function test_the_field_type_catalogue_drives_validation(): void
    {
        $form = Form::create(['title' => 'Survey', 'slug' => 'survey']);

        $this->post("/form-builder/{$form->id}/fields", ['label' => 'Weird', 'type' => 'jsonb'])
            ->assertSessionHasErrors('type');

        $this->assertArrayHasKey('text', Field::TYPES);
    }
}
