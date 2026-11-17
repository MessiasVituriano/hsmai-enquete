<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Option;
use App\Models\PollState;
use App\Models\Question;
use App\Models\Respondent;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            // o próprio cadastro (com o contato) só volta para o aparelho dono dele
            'me' => $respondent ? Respondent::where('token', $respondent)->first()?->toProfile() : null,
        ]);
    }

    /**
     * Cadastro (ou correção do cadastro) deste aparelho: nome, e-mail ou
     * WhatsApp, e hotel.
     *
     * Se o contato já é de outra pessoa cadastrada, é a mesma pessoa num
     * aparelho novo (trocou de celular, limpou o navegador): o aparelho adota
     * aquele cadastro e as respostas dele, em vez de duplicar a pessoa. Isso
     * só vale para quem ainda não tem cadastro; um cadastro existente que tenta
     * tomar o contato de outro recebe erro.
     */
    public function register(Request $request): JsonResponse
    {
        $token = $this->respondent($request);

        if (! $token) {
            return response()->json(['message' => 'Aparelho não identificado. Recarregue a página.'], 400);
        }

        $request->merge([
            'email' => $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null,
            'phone' => Respondent::normalizePhone($request->input('phone')),
            'name' => trim((string) $request->input('name')),
            'hotel' => trim((string) $request->input('hotel')),
        ]);

        $own = Respondent::where('token', $token)->first();

        // a unicidade só barra quem corrige o próprio cadastro; um cadastro novo
        // com contato já existente adota aquele cadastro (ver abaixo)
        $unique = fn (string $column) => $own ? [Rule::unique('respondents', $column)->ignore($own->id)] : [];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:120', ...$unique('email')],
            'phone' => ['required_without:email', 'nullable', 'digits_between:10,11', ...$unique('phone')],
            'hotel' => ['required', 'string', 'max:120'],
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required_without' => 'Informe um e-mail ou um WhatsApp.',
            'phone.required_without' => 'Informe um e-mail ou um WhatsApp.',
            'email.email' => 'Informe um e-mail válido.',
            'phone.digits_between' => 'Informe o WhatsApp com DDD.',
            'email.unique' => 'Este e-mail já está cadastrado em outro aparelho.',
            'phone.unique' => 'Este WhatsApp já está cadastrado em outro aparelho.',
            'hotel.required' => 'Informe o hotel.',
        ]);

        // só o contato escolhido fica: trocar de e-mail para WhatsApp apaga o e-mail
        $email = $data['email'] ?? null;
        $profile = [
            'name' => $data['name'],
            'email' => $email,
            'phone' => $email ? null : ($data['phone'] ?? null),
            'hotel' => $data['hotel'],
        ];

        $respondent = DB::transaction(function () use ($own, $token, $profile) {
            if ($own) {
                $own->update($profile);

                return $own;
            }

            $existing = Respondent::query()
                ->where($profile['email'] ? 'email' : 'phone', $profile['email'] ?? $profile['phone'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update($profile);

                return $existing;
            }

            return Respondent::create(['token' => $token, ...$profile]);
        });

        return response()->json([
            'token' => $respondent->token,
            'me' => $respondent->toProfile(),
        ], $own ? 200 : 201);
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

        if (! Respondent::where('token', $respondent)->exists()) {
            return response()->json(['message' => 'Faça seu cadastro para responder.'], 403);
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
