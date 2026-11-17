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
     * Troca o que o telão e os celulares mostram: o convite, a pergunta N para
     * responder, ou o resultado da pergunta N. Mostrar o resultado marca a
     * pergunta como revelada (o selo "liberada" do painel).
     */
    public function screen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen' => ['required', Rule::in(PollState::SCREENS)],
            'question_id' => ['required_unless:screen,invite', 'nullable', 'integer', 'exists:questions,id'],
        ]);

        DB::transaction(function () use ($data) {
            $state = PollState::current();
            $questionId = $data['screen'] === 'invite' ? null : (int) $data['question_id'];

            $state->update(['screen' => $data['screen'], 'screen_question_id' => $questionId]);

            if ($data['screen'] === 'results') {
                Question::whereKey($questionId)->whereNull('revealed_at')->update(['revealed_at' => now()]);
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
