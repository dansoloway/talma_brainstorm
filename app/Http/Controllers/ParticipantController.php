<?php

namespace App\Http\Controllers;

use App\Actions\DeselectPrompt;
use App\Actions\SaveResponse;
use App\Actions\SelectPrompt;
use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function home(): View
    {
        $stages = Stage::ordered()
            ->with(['prompts' => fn ($q) => $q->where('is_hidden', false)->orderBy('sort')->orderBy('id')])
            ->get();

        if ($stages->isEmpty()) {
            return view('participant.waiting');
        }

        return view('participant.toc', [
            'participant' => $this->participant(),
            'stages' => $stages,
            'firstStage' => $stages->first(),
        ]);
    }

    public function stage(Stage $stage): View
    {
        $participant = $this->participant();

        return view('participant.stage', $this->stageData($stage, $participant));
    }

    /** Organizer preview of a stage, exactly as participants see it, without saving anything. */
    public function preview(Stage $stage): View
    {
        return view('participant.stage', $this->stageData($stage, null));
    }

    /** Organizer read-only view of a participant's stages (does not use the participant guard). */
    public function viewAs(Participant $participant): RedirectResponse
    {
        $first = Stage::ordered()->first();

        abort_unless($first, 404, __('No stages have been set up yet.'));

        return redirect()->route('admin.view-as.stage', [$participant, $first]);
    }

    public function viewAsStage(Participant $participant, Stage $stage): View
    {
        return view('participant.stage', $this->stageData($stage, $participant, viewingAs: true));
    }

    public function select(Prompt $prompt, SelectPrompt $select): JsonResponse
    {
        $response = $select($this->participant(), $prompt);

        return response()->json([
            'selected' => true,
            'body' => $response->body,
            'selected_count' => $this->selectedCount($prompt->stage_id),
        ]);
    }

    public function deselect(Prompt $prompt, DeselectPrompt $deselect): JsonResponse
    {
        $deselect($this->participant(), $prompt);

        return response()->json([
            'selected' => false,
            'selected_count' => $this->selectedCount($prompt->stage_id),
        ]);
    }

    /** The autosave endpoint. Must stay tiny: see SaveResponse. */
    public function save(Request $request, int $prompt, SaveResponse $save): JsonResponse
    {
        $data = $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:100000'],
            'clear' => ['sometimes', 'boolean'],
            'client_seq' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $result = $save(
            $this->participant(),
            $prompt,
            (string) ($data['body'] ?? ''),
            (bool) ($data['clear'] ?? false),
            isset($data['client_seq']) ? (int) $data['client_seq'] : null,
        );

        return response()
            ->json([
                'saved_at' => $result['saved_at']?->toIso8601String(),
                'applied' => $result['applied'],
                'body' => $result['body'],
            ])
            ->header('Cache-Control', 'no-store');
    }

    /** Keep-alive: keeps the session fresh and hands back a current CSRF token. */
    public function ping(Request $request): JsonResponse
    {
        return response()
            ->json(['csrf' => $request->session()->token()])
            ->header('Cache-Control', 'no-store');
    }

    public function done(): RedirectResponse
    {
        $participant = $this->participant();
        $participant->forceFill(['finished_at' => now()])->save();

        return redirect()->route('participant.finished');
    }

    public function finished(): View
    {
        return view('participant.finished', [
            'participant' => $this->participant(),
            'lastStage' => Stage::ordered()->get()->last(),
        ]);
    }

    private function participant(): Participant
    {
        /** @var Participant */
        return Auth::guard('participant')->user();
    }

    private function selectedCount(int $stageId): int
    {
        return $this->participant()->responses()
            ->where('responses.stage_id', $stageId)
            ->where('is_selected', true)
            ->count();
    }

    private function stageData(Stage $stage, ?Participant $participant, bool $viewingAs = false): array
    {
        $stages = Stage::ordered()->get(['id', 'title', 'sort']);
        $index = $stages->search(fn ($s) => $s->id === $stage->id);

        $responses = $participant
            ? $participant->responses()->where('responses.stage_id', $stage->id)->get()->keyBy('prompt_id')
            : collect();

        return [
            'participant' => $participant,
            'preview' => $viewingAs || $participant === null,
            'viewingAs' => $viewingAs,
            'stage' => $stage,
            'stages' => $stages,
            'number' => $index + 1,
            'previous' => $stages->get($index - 1 >= 0 ? $index - 1 : -1),
            'next' => $stages->get($index + 1),
            'prompts' => $stage->visiblePrompts()->get(),
            'responses' => $responses,
            'maxPerStage' => SelectPrompt::MAX_PER_STAGE,
        ];
    }
}
