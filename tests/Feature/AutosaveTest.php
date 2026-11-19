<?php

use App\Actions\SaveResponse;
use App\Actions\SelectPrompt;
use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->prompt = Prompt::factory()->create();
    $this->me = Participant::factory()->create();
    app(SelectPrompt::class)($this->me, $this->prompt);
});

function saveAs($test, Participant $p, int $promptId, string $body, bool $clear = false, ?int $clientSeq = null)
{
    $payload = ['body' => $body, 'clear' => $clear];
    if ($clientSeq !== null) {
        $payload['client_seq'] = $clientSeq;
    }

    return $test->actingAs($p, 'participant')
        ->putJson(route('participant.save', $promptId), $payload);
}

it('saves text over HTTP and marks the route as uncacheable', function () {
    saveAs($this, $this->me, $this->prompt->id, 'Hello')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(Response::sole()->body)->toBe('Hello');
});

it('saving the same text twice creates one row and does not bump last_saved_at', function () {
    $this->travelTo(now()->setTime(10, 0));
    saveAs($this, $this->me, $this->prompt->id, 'Same text')->assertOk();
    $first = Response::sole()->last_saved_at;

    $this->travelTo(now()->setTime(10, 5));
    saveAs($this, $this->me, $this->prompt->id, 'Same text')->assertOk();

    expect(Response::count())->toBe(1)
        ->and(Response::sole()->last_saved_at->equalTo($first))->toBeTrue();
});

it('many quick saves for one prompt still leave exactly one row', function () {
    foreach (range(1, 25) as $i) {
        saveAs($this, $this->me, $this->prompt->id, str_repeat('word ', $i))->assertOk();
    }

    expect(Response::count())->toBe(1)
        ->and(Response::sole()->body)->toBe(str_repeat('word ', 25));
});

it('an empty payload does not wipe existing text unless clear is set', function () {
    saveAs($this, $this->me, $this->prompt->id, 'Important words')->assertOk();

    saveAs($this, $this->me, $this->prompt->id, '')
        ->assertOk()
        ->assertJson(['applied' => false, 'body' => 'Important words']);
    expect(Response::sole()->body)->toBe('Important words');

    saveAs($this, $this->me, $this->prompt->id, '', clear: true)
        ->assertOk()
        ->assertJson(['applied' => true, 'body' => '']);
    expect(Response::sole()->body)->toBe('');
});

it('ignores an overlapping save with an older client_seq', function () {
    saveAs($this, $this->me, $this->prompt->id, 'First draft', clientSeq: 1)
        ->assertOk()
        ->assertJson(['applied' => true]);

    $this->actingAs($this->me, 'participant')
        ->putJson(route('participant.save', $this->prompt->id), [
            'body' => 'Newest draft',
            'client_seq' => 3,
        ])
        ->assertOk()
        ->assertJson(['applied' => true, 'body' => 'Newest draft']);

    $this->actingAs($this->me, 'participant')
        ->putJson(route('participant.save', $this->prompt->id), [
            'body' => 'Stale keepalive draft',
            'client_seq' => 2,
        ])
        ->assertOk()
        ->assertJson(['applied' => false, 'body' => 'Newest draft']);

    expect(Response::sole()->body)->toBe('Newest draft')
        ->and(Response::sole()->save_seq)->toBe(3);
});

it('keeps text even if it arrives for a prompt that was never selected', function () {
    $other = Prompt::factory()->create();

    saveAs($this, $this->me, $other->id, 'Typed before select finished')->assertOk();

    $row = Response::where('prompt_id', $other->id)->sole();
    expect($row->body)->toBe('Typed before select finished')
        ->and($row->is_selected)->toBeFalse();
});

it('preserves Hebrew, emoji and line breaks exactly', function () {
    $text = "שלום עולם 👋\n\n  Second line, with \"quotes\"  \r\nסוף";
    saveAs($this, $this->me, $this->prompt->id, $text)->assertOk();

    expect(Response::sole()->body)->toBe($text);
});

it('the save path runs a small fixed number of queries and sends no mail', function () {
    Mail::fake();
    saveAs($this, $this->me, $this->prompt->id, 'warm up')->assertOk();

    DB::enableQueryLog();
    app(SaveResponse::class)($this->me, $this->prompt->id, 'next words', false, 10);
    $queries = DB::getQueryLog();

    expect(count($queries))->toBeLessThanOrEqual(2); // one read + one write
    Mail::assertNothingSent();
});
