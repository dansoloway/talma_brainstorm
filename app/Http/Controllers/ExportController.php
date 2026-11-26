<?php

namespace App\Http\Controllers;

use App\Models\Response;
use Illuminate\Http\Request;
use League\Csv\Bom;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export: one row per response. Filters: ?prompt=ID, ?participant=ID, ?unselected=1.
 * Organizer-only (auth:web).
 */
class ExportController extends Controller
{
    public const HEADER = ['participant', 'stage_number', 'stage_title', 'prompt_id', 'prompt', 'language', 'selected', 'response', 'last_saved_at'];

    public function __invoke(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        $name = 'responses';
        if ($request->filled('prompt')) $name .= '-prompt-'.(int) $request->input('prompt');
        if ($request->filled('participant')) $name .= '-participant-'.(int) $request->input('participant');
        $name .= '-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            $this->write($query, $out);
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function query(Request $request)
    {
        return Response::query()
            ->with(['participant:id,name', 'prompt:id,language', 'stage:id,title,sort'])
            ->join('stages', 'stages.id', '=', 'responses.stage_id')
            ->join('participants', 'participants.id', '=', 'responses.participant_id')
            ->join('prompts', 'prompts.id', '=', 'responses.prompt_id')
            ->select('responses.*')
            ->when($request->filled('prompt'), fn ($q) => $q->where('responses.prompt_id', (int) $request->input('prompt')))
            ->when($request->filled('participant'), fn ($q) => $q->where('responses.participant_id', (int) $request->input('participant')))
            ->when(! $request->boolean('unselected'), fn ($q) => $q->where('responses.is_selected', true))
            ->orderBy('stages.sort')->orderBy('stages.id')
            ->orderBy('prompts.sort')->orderBy('prompts.id')
            ->orderBy('participants.name');
    }

    /** @param resource $stream */
    public function write($query, $stream): void
    {
        $stageNumbers = \App\Models\Stage::ordered()->pluck('id')->flip()->map(fn ($i) => $i + 1);

        fwrite($stream, Bom::Utf8->value); // so Excel shows Hebrew correctly
        $csv = Writer::from($stream);
        $csv->insertOne(self::HEADER);

        $query->chunk(500, function ($rows) use ($csv, $stageNumbers) {
            foreach ($rows as $r) {
                $csv->insertOne([
                    $r->participant->name,
                    $stageNumbers[$r->stage_id] ?? '',
                    $r->stage->title,
                    $r->prompt_id,
                    filled($r->prompt_details_snapshot)
                        ? $r->prompt_body_snapshot."\n\n".$r->prompt_details_snapshot
                        : $r->prompt_body_snapshot,
                    $r->prompt->language->value,
                    $r->is_selected ? 'yes' : 'no',
                    $r->displayBody(),
                    $r->last_saved_at?->toDateTimeString(),
                ]);
            }
        });
    }
}
