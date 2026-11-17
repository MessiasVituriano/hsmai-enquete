<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\PollState;
use App\Models\Question;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private PollService $poll) {}

    /** Poll do painel: tudo do telão, mais os resultados ao vivo de todas as perguntas. */
    public function overview(): JsonResponse
    {
        $state = PollState::current();

        return response()->json([
            ...$this->poll->stage($state),
            'questions' => $this->poll->serializeQuestions($this->poll->questions(), $this->poll->counts(), true),
        ]);
    }

    /**
     * Troca o que o telão mostra. Levar o resultado de uma pergunta ao telão
     * também o libera nos celulares — a sala já viu, não há o que esconder.
     */
    public function screen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen' => ['required', Rule::in(PollState::SCREENS)],
            'question_id' => ['required_if:screen,question', 'nullable', 'integer', 'exists:questions,id'],
        ]);

        DB::transaction(function () use ($data) {
            $state = PollState::current();
            $questionId = $data['screen'] === 'question' ? (int) $data['question_id'] : null;

            $state->update(['screen' => $data['screen'], 'screen_question_id' => $questionId]);

            $reveal = match ($data['screen']) {
                'question' => Question::whereKey($questionId),
                'all' => Question::query(),
                default => null,
            };

            $reveal?->whereNull('revealed_at')->update(['revealed_at' => now()]);
        });

        return $this->overview();
    }

    /** Recolhe o resultado de uma pergunta (dos celulares e do telão). */
    public function hide(Question $question): JsonResponse
    {
        DB::transaction(function () use ($question) {
            $question->update(['revealed_at' => null]);

            $state = PollState::current();

            if ($state->screen === 'all' || $state->screen_question_id === $question->id) {
                $state->update(['screen' => 'invite', 'screen_question_id' => null]);
            }
        });

        return $this->overview();
    }

    public function accepting(Request $request): JsonResponse
    {
        $data = $request->validate(['accepting' => ['required', 'boolean']]);

        PollState::current()->update(['accepting' => $data['accepting']]);

        return $this->overview();
    }

    /** Zera tudo para o próximo uso: respostas, liberações e o telão volta ao convite. */
    public function reset(): JsonResponse
    {
        DB::transaction(function () {
            Answer::query()->delete();
            Question::query()->update(['revealed_at' => null]);
            PollState::current()->update(['accepting' => true, 'screen' => 'invite', 'screen_question_id' => null]);
        });

        return $this->overview();
    }
}
