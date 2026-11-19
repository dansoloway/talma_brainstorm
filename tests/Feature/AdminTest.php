<?php

use App\Filament\Resources\Participants\ParticipantResource;
use App\Filament\Resources\Prompts\PromptResource;
use App\Filament\Resources\Stages\RelationManagers\PromptsRelationManager;
use App\Filament\Resources\Stages\Pages\EditStage;
use App\Filament\Resources\Stages\StageResource;
use App\Models\Response;
use App\Models\Stage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'email' => 'daniel@talmaisrael.com',
    ]));
    $this->response = Response::factory()->create();
});

it('renders every admin page', function () {
    $this->get('/admin')->assertOk();
    $this->get(StageResource::getUrl('index'))->assertOk();
    $this->get(StageResource::getUrl('edit', ['record' => $this->response->stage_id]))->assertOk();
    $this->get(PromptResource::getUrl('index'))->assertOk();
    $this->get(PromptResource::getUrl('view', ['record' => $this->response->prompt_id]))->assertOk();
    $this->get(ParticipantResource::getUrl('index'))->assertOk();
    $this->get(ParticipantResource::getUrl('view', ['record' => $this->response->participant_id]))->assertOk();
});

it('shows responses for a prompt and for a participant', function () {
    Livewire::test(\App\Filament\Resources\Prompts\RelationManagers\ResponsesRelationManager::class, [
        'ownerRecord' => $this->response->prompt, 'pageClass' => \App\Filament\Resources\Prompts\Pages\ViewPrompt::class,
    ])->assertOk()->assertSee($this->response->participant->name)->assertCanSeeTableRecords([$this->response]);

    Livewire::test(\App\Filament\Resources\Participants\RelationManagers\ResponsesRelationManager::class, [
        'ownerRecord' => $this->response->participant, 'pageClass' => \App\Filament\Resources\Participants\Pages\ViewParticipant::class,
    ])->assertOk()->assertCanSeeTableRecords([$this->response]);
});

it('bulk adds prompts, one per line, skipping blank lines', function () {
    $stage = Stage::factory()->create();

    Livewire::test(PromptsRelationManager::class, ['ownerRecord' => $stage, 'pageClass' => EditStage::class])
        ->callTableAction('bulkAdd', data: [
            'lines' => "First prompt\n\n  שאלה שנייה  \nThird",
            'language' => 'he',
        ])
        ->assertHasNoTableActionErrors();

    expect($stage->prompts()->pluck('body')->all())->toBe(['First prompt', 'שאלה שנייה', 'Third']);
});
