<?php

use App\Http\Controllers\ExportController;
use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use League\Csv\Reader;

it('the database refuses to delete a prompt that has responses', function () {
    $response = Response::factory()->create();

    expect(fn () => $response->prompt->delete())->toThrow(QueryException::class);
    expect(Prompt::count())->toBe(1);
});

it('the database refuses to delete a participant that has responses', function () {
    $response = Response::factory()->create();

    expect(fn () => $response->participant->delete())->toThrow(QueryException::class);
});

it('a unique index blocks duplicate rows for the same participant and prompt', function () {
    $r = Response::factory()->create();

    expect(fn () => Response::factory()->create([
        'participant_id' => $r->participant_id,
        'prompt_id' => $r->prompt_id,
    ]))->toThrow(QueryException::class);
});

it('exports CSV that survives Hebrew, commas, quotes and line breaks', function () {
    $tricky = Response::factory()->trickyText()->create();
    Response::factory()->create(['is_selected' => false]); // hidden by default

    $stream = fopen('php://temp', 'w+');
    $controller = new ExportController;
    $controller->write($controller->query(Request::create('/')), $stream);
    rewind($stream);
    $raw = stream_get_contents($stream);

    expect(str_starts_with($raw, "\xEF\xBB\xBF"))->toBeTrue(); // BOM for Excel

    $reader = Reader::fromString($raw);
    $reader->setHeaderOffset(0);
    $rows = iterator_to_array($reader->getRecords());

    expect($rows)->toHaveCount(1)
        ->and(array_values($rows)[0]['response'])->toBe($tricky->body)
        ->and(array_values($rows)[0]['participant'])->toBe($tricky->participant->name);
});

it('export can filter by prompt and participant, and include dropped responses', function () {
    $a = Response::factory()->create();
    $b = Response::factory()->create(['is_selected' => false]);

    $this->actingAs(User::factory()->create());

    $count = function (array $query) {
        $content = $this->get(route('admin.export', $query))->assertOk()->streamedContent();
        return count(Reader::fromString($content)->setHeaderOffset(0)->jsonSerialize());
    };

    expect($count([]))->toBe(1)
        ->and($count(['unselected' => 1]))->toBe(2)
        ->and($count(['prompt' => $a->prompt_id]))->toBe(1)
        ->and($count(['participant' => $b->participant_id, 'unselected' => 1]))->toBe(1);
});
