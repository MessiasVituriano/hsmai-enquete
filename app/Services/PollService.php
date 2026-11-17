<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\PollState;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Monta os payloads das três telas a partir do mesmo lugar, para que celular,
 * telão e painel nunca discordem sobre um número.
 */
class PollService
{
    /** @return Collection<int, Question> */
    public function questions(): Collection
    {
        return Question::with('options')->orderBy('position')->get();
    }

    /** @return array<int, array<int, int>> question_id => [option_id => votos] */
    public function counts(): array
    {
        $counts = [];

        Answer::query()
            ->selectRaw('question_id, option_id, count(*) as total')
            ->groupBy('question_id', 'option_id')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[(int) $row->question_id][(int) $row->option_id] = (int) $row->total;
            });

        return $counts;
    }

    /** Pessoas (aparelhos) que responderam pelo menos uma pergunta. */
    public function respondents(): int
    {
        return Answer::query()->distinct()->count('respondent');
    }

    /**
     * @param  bool|callable(Question): bool  $withResults
     * @return list<array<string, mixed>>
     */
    public function serializeQuestions(Collection $questions, array $counts, bool|callable $withResults): array
    {
        return $questions->map(function (Question $question) use ($counts, $withResults) {
            $show = is_callable($withResults) ? $withResults($question) : $withResults;

            return [
                'id' => $question->id,
                'number' => $question->position,
                'prompt' => $question->prompt,
                'inline_options' => $question->inline_options,
                'revealed' => $question->revealed_at !== null,
                'options' => $question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                ])->values()->all(),
                'results' => $show ? $this->results($question, $counts[$question->id] ?? []) : null,
            ];
        })->values()->all();
    }

    /**
     * Percentuais pelo maior resto: a soma dá sempre 100, e o telão não mostra
     * um "Sim 33% · Não 66%" que a sala vai notar.
     *
     * @param  array<int, int>  $counts
     * @return array{total: int, options: list<array{id: int, count: int, percent: int}>}
     */
    public function results(Question $question, array $counts): array
    {
        $total = array_sum($counts);
        $rows = [];

        foreach ($question->options as $option) {
            $count = $counts[$option->id] ?? 0;
            $exact = $total > 0 ? $count * 100 / $total : 0;
            $rows[] = ['id' => $option->id, 'count' => $count, 'percent' => (int) floor($exact), 'rest' => $exact - floor($exact)];
        }

        if ($total > 0) {
            $missing = 100 - array_sum(array_column($rows, 'percent'));
            $order = array_keys($rows);
            usort($order, fn ($a, $b) => $rows[$b]['rest'] <=> $rows[$a]['rest']);

            foreach (array_slice($order, 0, $missing) as $i) {
                $rows[$i]['percent']++;
            }
        }

        return [
            'total' => $total,
            'options' => array_map(fn ($r) => ['id' => $r['id'], 'count' => $r['count'], 'percent' => $r['percent']], $rows),
        ];
    }

    /** O que o telão (e o painel, por cima disso) precisam para desenhar o slide. */
    public function stage(PollState $state): array
    {
        return [
            'event_name' => config('enquete.event_name'),
            'title' => config('enquete.title'),
            'subtitle' => config('enquete.subtitle'),
            'call_to_action' => config('enquete.call_to_action'),
            'call_to_action_note' => config('enquete.call_to_action_note'),
            'logos' => config('enquete.logos'),
            'accepting' => $state->accepting,
            'screen' => $state->screen,
            'screen_question_id' => $state->screen_question_id,
            'respondents' => $this->respondents(),
        ];
    }
}
