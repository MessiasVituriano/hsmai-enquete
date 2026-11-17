<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PollState;
use App\Models\Question;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;

class DisplayController extends Controller
{
    /**
     * Poll do telão. Leva só a pergunta em foco (nunca a lista inteira) e o
     * resultado dela só na fase de resultado.
     */
    public function __invoke(PollService $poll): JsonResponse
    {
        $state = PollState::current();

        return response()->json([
            ...$poll->stage($state),
            'total_questions' => Question::count(),
            'questions' => $poll->serializeQuestions(
                $poll->questions()->where('id', $state->focusId())->values(),
                $poll->counts(),
                fn (Question $q) => $q->revealed_at !== null && $state->screen === 'results',
            ),
        ]);
    }
}
