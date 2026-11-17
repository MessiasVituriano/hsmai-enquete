<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\PollState;
use App\Models\Question;
use App\Models\Respondent;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Os cadastros, do mais recente ao mais antigo, com o que cada um respondeu.
     * Fica fora do poll de 1s do overview: o painel busca a cada poucos segundos.
     */
    public function respondents(): JsonResponse
    {
        $questions = $this->poll->questions();
        $labels = $questions->flatMap->options->pluck('label', 'id');

        $rows = Respondent::with('answers')->latest('id')->get()->map(fn (Respondent $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => Respondent::formatPhone($r->phone),
            'hotel' => $r->hotel,
            'created_at' => $r->created_at?->toIso8601String(),
            'answers' => $questions->map(fn ($q) => $labels[$r->answers->firstWhere('question_id', $q->id)?->option_id] ?? null)->all(),
        ]);

        return response()->json(['respondents' => $rows]);
    }

    /**
     * A planilha dos cadastros com as respostas. Separador ";" e BOM UTF-8:
     * é o que o Excel em português abre direto, com acento e em colunas.
     */
    public function respondentsCsv(): StreamedResponse
    {
        $questions = $this->poll->questions();
        $labels = $questions->flatMap->options->pluck('label', 'id');
        $filename = 'enquete-participantes-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($questions, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, [
                'Nome', 'E-mail', 'WhatsApp', 'Hotel', 'Cadastro em',
                ...$questions->map(fn ($q) => 'Pergunta '.$q->position)->all(),
            ], ';');

            Respondent::with('answers')->orderBy('id')->chunk(500, function ($chunk) use ($out, $questions, $labels) {
                foreach ($chunk as $r) {
                    fputcsv($out, [
                        $r->name,
                        $r->email ?? '',
                        Respondent::formatPhone($r->phone),
                        $r->hotel,
                        $r->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                        ...$questions->map(fn ($q) => $labels[$r->answers->firstWhere('question_id', $q->id)?->option_id] ?? '')->all(),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Zera tudo para o próximo uso: cadastros, respostas, liberações, e o telão volta ao convite. */
    public function reset(): JsonResponse
    {
        DB::transaction(function () {
            Answer::query()->delete();
            Respondent::query()->delete();
            Question::query()->update(['revealed_at' => null]);
            PollState::current()->update(['accepting' => true, 'screen' => 'invite', 'screen_question_id' => null]);
        });

        return $this->overview();
    }
}
