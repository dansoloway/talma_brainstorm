<?php

use App\Actions\DeselectPrompt;
use App\Actions\SaveResponse;
use App\Actions\SelectPrompt;
use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use App\Models\Stage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->prompts = Prompt::factory()->count(10)->create(['stage_id' => $this->stage->id]);
    $this->me = Participant::factory()->create();
});

it('allows 5 selected prompts on a stage and rejects a 6th', function () {
    $select = app(SelectPrompt::class);
    $this->prompts->take(5)->each(fn ($p) => $select($this->me, $p));

    expect(Response::where('is_selected', true)->count())->toBe(5);

    expect(fn () => $select($this->me, $this->prompts[5]))->toThrow(ValidationException::class);
    expect(Response::where('is_selected', true)->count())->toBe(5);
});

it('rejects a 6th selection over HTTP with a 422', function () {
    $this->prompts->take(5)->each(fn ($p) => $this->actingAs($this->me, 'participant')
        ->postJson(route('participant.select', $p))->assertOk());

    $this->actingAs($this->me, 'participant')
        ->postJson(route('participant.select', $this->prompts[5]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('prompt');
});

it('counts the limit per stage, not overall', function () {
    $select = app(SelectPrompt::class);
    $this->prompts->take(5)->each(fn ($p) => $select($this->me, $p));

    $other = Prompt::factory()->create(); // a different stage
    $select($this->me, $other);

    expect(Response::where('is_selected', true)->count())->toBe(6);
});

it('frees a slot when a prompt is dropped', function () {
    $select = app(SelectPrompt::class);
    $this->prompts->take(5)->each(fn ($p) => $select($this->me, $p));
    app(DeselectPrompt::class)($this->me, $this->prompts[0]);

    $select($this->me, $this->prompts[5]);

    expect(Response::where('is_selected', true)->count())->toBe(5);
});

it('rejects selecting a hidden prompt', function () {
    $hidden = Prompt::factory()->hidden()->create(['stage_id' => $this->stage->id]);

    expect(fn () => app(SelectPrompt::class)($this->me, $hidden))->toThrow(ValidationException::class);
});

it('still counts a selected prompt toward the max after it is hidden', function () {
    $select = app(SelectPrompt::class);
    $this->prompts->take(5)->each(fn ($p) => $select($this->me, $p));

    $this->prompts[0]->update(['is_hidden' => true]);

    expect(fn () => $select($this->me, $this->prompts[5]))->toThrow(ValidationException::class);
    expect(Response::where('is_selected', true)->count())->toBe(5);
});

it('keeps the text when a prompt is dropped and restores it when re-picked', function () {
    $prompt = $this->prompts[0];
    app(SelectPrompt::class)($this->me, $prompt);
    app(SaveResponse::class)($this->me, $prompt->id, 'My precious paragraph.');

    app(DeselectPrompt::class)($this->me, $prompt);

    $row = Response::sole();
    expect($row->is_selected)->toBeFalse()
        ->and($row->body)->toBe('My precious paragraph.');

    $restored = app(SelectPrompt::class)($this->me, $prompt);
    expect($restored->is_selected)->toBeTrue()
        ->and($restored->body)->toBe('My precious paragraph.')
        ->and(Response::count())->toBe(1);
});

it('stores a snapshot of the prompt text on the response', function () {
    $prompt = $this->prompts[0];
    app(SelectPrompt::class)($this->me, $prompt);
    $original = $prompt->body;

    $prompt->update(['body' => 'Edited later']);

    expect(Response::sole()->prompt_body_snapshot)->toBe($original);
});
