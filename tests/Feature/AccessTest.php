<?php

use App\Actions\SelectPrompt;
use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use App\Models\Stage;
use App\Models\User;

it('shows the Google sign-in page for guests', function () {
    $this->get(route('participant.login'))
        ->assertOk()
        ->assertSee(__('Sign in to write'))
        ->assertSee(route('auth.google.participant'), false);
});

it('sends guests to the login page, and API guests get 401', function () {
    $stage = Stage::factory()->create();
    $prompt = Prompt::factory()->create();

    $this->get(route('participant.stage', $stage))->assertRedirect(route('participant.login'));
    $this->putJson(route('participant.save', $prompt->id), ['body' => 'x'])->assertUnauthorized();
});

it('signed-in participants land on the first stage from home', function () {
    $stage = Stage::factory()->create();
    $p = Participant::factory()->create();

    $this->actingAs($p, 'participant')
        ->get(route('participant.home'))
        ->assertRedirect(route('participant.stage', $stage));
});

it('one participant cannot overwrite another participant\'s response', function () {
    $prompt = Prompt::factory()->create();
    $alice = Participant::factory()->create();
    $bob = Participant::factory()->create();

    app(SelectPrompt::class)($alice, $prompt);
    $this->actingAs($alice, 'participant')
        ->putJson(route('participant.save', $prompt->id), ['body' => 'Alice wrote this'])->assertOk();

    // Bob tries to sneak a participant_id in; it must be ignored.
    $this->actingAs($bob, 'participant')
        ->putJson(route('participant.save', $prompt->id), ['body' => 'Bob wrote this', 'participant_id' => $alice->id])
        ->assertOk();

    expect(Response::where('participant_id', $alice->id)->sole()->body)->toBe('Alice wrote this')
        ->and(Response::where('participant_id', $bob->id)->sole()->body)->toBe('Bob wrote this');
});

it('a participant only sees their own text on the stage page', function () {
    $prompt = Prompt::factory()->create();
    $alice = Participant::factory()->create();
    $bob = Participant::factory()->create();
    app(SelectPrompt::class)($alice, $prompt);
    $this->actingAs($alice, 'participant')->putJson(route('participant.save', $prompt->id), ['body' => 'SECRET-ALICE']);

    $this->actingAs($bob, 'participant')
        ->get(route('participant.stage', $prompt->stage))
        ->assertOk()
        ->assertDontSee('SECRET-ALICE');
});

it('going back and forward loads the right saved text', function () {
    $s1 = Stage::factory()->create();
    $s2 = Stage::factory()->create();
    $p1 = Prompt::factory()->create(['stage_id' => $s1->id]);
    $p2 = Prompt::factory()->hebrew()->create(['stage_id' => $s2->id]);
    $me = Participant::factory()->create();

    $this->actingAs($me, 'participant');
    $this->postJson(route('participant.select', $p1));
    $this->putJson(route('participant.save', $p1->id), ['body' => 'Stage one text']);
    $this->postJson(route('participant.select', $p2));
    $this->putJson(route('participant.save', $p2->id), ['body' => 'טקסט של שלב שתיים']);

    $this->get(route('participant.stage', $s1))->assertSee('Stage one text')->assertDontSee('טקסט של שלב שתיים');
    $this->get(route('participant.stage', $s2))->assertSee('טקסט של שלב שתיים')->assertSee('dir="rtl"', false);
    $this->get(route('participant.stage', $s1))->assertSee('Stage one text');
});

it('participants cannot open the admin panel, preview, view-as or export', function () {
    $stage = Stage::factory()->create();
    $target = Participant::factory()->create();
    $this->actingAs(Participant::factory()->create(), 'participant');

    $this->get('/admin')->assertRedirect();
    $this->get(route('admin.preview', $stage))->assertRedirect();
    $this->get(route('admin.view-as', $target))->assertRedirect();
    $this->get(route('admin.view-as.stage', [$target, $stage]))->assertRedirect();
    $this->get(route('admin.export'))->assertRedirect();
});

it('guests are sent to admin login for view-as', function () {
    $stage = Stage::factory()->create();
    $participant = Participant::factory()->create();

    $this->get(route('admin.view-as', $participant))
        ->assertRedirect(route('filament.admin.auth.login'));
    $this->get(route('admin.view-as.stage', [$participant, $stage]))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('organizers can preview a stage without creating responses', function () {
    $prompt = Prompt::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.preview', $prompt->stage))
        ->assertOk()
        ->assertSee($prompt->body)
        ->assertSee('Preview');

    expect(Response::count())->toBe(0);
});

it('organizers can view-as a participant read-only without changing responses', function () {
    $prompt = Prompt::factory()->create();
    $participant = Participant::factory()->create(['name' => 'Dana']);
    app(SelectPrompt::class)($participant, $prompt);
    $response = Response::where('participant_id', $participant->id)->where('prompt_id', $prompt->id)->sole();
    $response->forceFill(['body' => 'Dana secret answer', 'last_saved_at' => now()])->save();
    $savedAt = $response->fresh()->last_saved_at;

    $organizer = User::factory()->create();

    $this->actingAs($organizer)
        ->get(route('admin.view-as', $participant))
        ->assertRedirect(route('admin.view-as.stage', [$participant, $prompt->stage]));

    $this->actingAs($organizer)
        ->get(route('admin.view-as.stage', [$participant, $prompt->stage]))
        ->assertOk()
        ->assertSee('Dana')
        ->assertSee('Dana secret answer')
        ->assertSee('Viewing as Dana (read-only)')
        ->assertSee('Leave')
        ->assertDontSee('Done');

    expect(Response::count())->toBe(1)
        ->and($response->fresh()->body)->toBe('Dana secret answer')
        ->and($response->fresh()->last_saved_at->eq($savedAt))->toBeTrue();
});

it('the Done button records a finish time', function () {
    $me = Participant::factory()->create();

    $this->actingAs($me, 'participant')->post(route('participant.done'))->assertRedirect(route('participant.finished'));

    expect($me->fresh()->finished_at)->not->toBeNull();
});

it('the keep-alive ping returns a CSRF token', function () {
    $this->actingAs(Participant::factory()->create(), 'participant')
        ->getJson(route('participant.ping'))
        ->assertOk()
        ->assertJsonStructure(['csrf']);
});
