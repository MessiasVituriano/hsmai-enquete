<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PollState;
use App\Models\Question;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;

class DisplayController extends Controller
{
    /** Poll do telão. Só leva resultado de pergunta que o facilitador já liberou. */
    public function __invoke(PollService $poll): JsonResponse
    {
        $state = PollState::current();

        return response()->json([
            ...$poll->stage($state),
            'questions' => $poll->serializeQuestions(
                $poll->questions(),
                $poll->counts(),
                fn (Question $q) => $q->revealed_at !== null,
            ),
        ]);
    }
}
