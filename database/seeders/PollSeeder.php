<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\PollState;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza o banco com config/enquete.php, pela posição.
 *
 * Idempotente: rodar de novo atualiza textos sem duplicar nada e sem apagar
 * respostas — por isso o entrypoint do container pode rodá-lo a cada boot.
 * Só some o que saiu do config (pergunta ou alternativa), levando os votos
 * dela junto.
 */
class PollSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            PollState::current();

            $questions = array_values(config('enquete.questions', []));

            foreach ($questions as $i => $item) {
                $question = Question::updateOrCreate(
                    ['position' => $i + 1],
                    [
                        'prompt' => $item['prompt'],
                        'inline_options' => (bool) ($item['inline_options'] ?? false),
                    ],
                );

                $labels = array_values($item['options']);

                foreach ($labels as $j => $label) {
                    Option::updateOrCreate(
                        ['question_id' => $question->id, 'position' => $j + 1],
                        ['label' => $label],
                    );
                }

                $question->options()->where('position', '>', count($labels))->delete();
            }

            Question::where('position', '>', count($questions))->delete();
        });
    }
}
