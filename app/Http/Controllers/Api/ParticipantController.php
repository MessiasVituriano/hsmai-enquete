<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Option;
use App\Models\PollState;
use App\Models\Question;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParticipantController extends Controller
{
    public function __construct(private PollService $poll) {}

    /**
     * Poll do celular: só a pergunta que o facilitador selecionou, o que este
     * aparelho já marcou e, se ela estiver na fase de resultado, o resultado.
     *
     * O celular espelha o telão: no convite não há pergunta (a pessoa
     * aguarda); com a pergunta N sendo respondida ou com o resultado dela na
     * tela, chega só ela. A lista inteira nunca vai ao celular.
     */
    public function show(Request $request): JsonResponse
    {
        $state = PollState::current();
        $all = $this->poll->questions();
        $questions = $all->where('id', $state->focusId())->values();
        $respondent = $this->respondent($request);

        $mine = $respondent
            ? Answer::where('respondent', $respondent)->pluck('option_id', 'question_id')
            : collect();

        return response()->json([
            'event_name' => config('enquete.event_name'),
            'title' => config('enquete.title'),
            'accepting' => $state->accepting,
            'screen' => $questions->isEmpty() ? 'invite' : $state->screen,
            'total_questions' => $all->count(),
            'questions' => $this->poll->serializeQuestions(
                $questions,
                $this->poll->counts(),
                fn (Question $q) => $q->revealed_at !== null && $state->screen === 'results',
            ),
            'mine' => (object) $mine->all(),
        ]);
    }

    /**
     * Marca (ou troca) a alternativa de uma pergunta. Mudar de ideia vale até a
     * enquete fechar.
     *
     * Aceita qualquer pergunta, não só a vigente: se o facilitador troca o
     * telão no segundo em que alguém tocou, o voto não se perde.
     */
    public function answer(Request $request): JsonResponse
    {
        $respondent = $this->respondent($request);

        if (! $respondent) {
            return response()->json(['message' => 'Aparelho não identificado. Recarregue a página.'], 400);
        }

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['required', 'integer'],
        ]);

        $option = Option::where('id', $data['option_id'])
            ->where('question_id', $data['question_id'])
            ->first();

        if (! $option) {
            return response()->json(['message' => 'Essa alternativa não pertence à pergunta.'], 422);
        }

        if (! PollState::current()->accepting) {
            return response()->json(['message' => 'A enquete está encerrada.'], 423);
        }

        // upsert sobre o índice único: dois toques no mesmo segundo viram uma
        // linha só, sem corrida entre o "existe?" e o insert
        $now = now();
        Answer::upsert(
            [[
                'respondent' => $respondent,
                'question_id' => $option->question_id,
                'option_id' => $option->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['respondent', 'question_id'],
            ['option_id', 'updated_at'],
        );

        return response()->json(['question_id' => $option->question_id, 'option_id' => $option->id]);
    }

    private function respondent(Request $request): ?string
    {
        $value = $request->header('X-Respondent');

        return is_string($value) && Str::isUuid($value) ? strtolower($value) : null;
    }
}
