<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\PollState;
use App\Models\Question;
use Database\Seeders\PollSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PollTest extends TestCase
{
    use RefreshDatabase;

    protected array $master = ['X-Master-Token' => 'master-dev-token'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['enquete.master_token' => 'master-dev-token']);
        $this->seed(PollSeeder::class);
    }

    protected function phone(): array
    {
        return ['X-Respondent' => (string) Str::uuid()];
    }

    protected function question(int $position): Question
    {
        return Question::with('options')->where('position', $position)->firstOrFail();
    }

    protected function vote(array $phone, int $position, int $option)
    {
        $q = $this->question($position);

        return $this->postJson('/api/answer', [
            'question_id' => $q->id,
            'option_id' => $q->options[$option]->id,
        ], $phone);
    }

    public function test_seed_carrega_as_quatro_perguntas_do_slide(): void
    {
        $this->assertSame(4, Question::count());
        $this->assertSame(['Sim', 'Não'], $this->question(1)->options->pluck('label')->all());
        $this->assertSame(
            ['Comissão', 'Mark-up', 'Volume de pernoites', 'Facilidade de integração e distribuição'],
            $this->question(4)->options->pluck('label')->all(),
        );
        $this->assertTrue($this->question(4)->inline_options);
    }

    public function test_seed_e_idempotente_e_preserva_respostas(): void
    {
        $this->vote($this->phone(), 1, 0)->assertOk();

        config(['enquete.questions.0.prompt' => 'Texto revisado']);
        $this->seed(PollSeeder::class);

        $this->assertSame(4, Question::count());
        $this->assertSame('Texto revisado', $this->question(1)->prompt);
        $this->assertSame(1, Answer::count());
    }

    public function test_trocar_a_resposta_nao_duplica_o_voto(): void
    {
        $phone = $this->phone();

        $this->vote($phone, 1, 0)->assertOk();
        $this->vote($phone, 1, 1)->assertOk();

        $this->assertSame(1, Answer::count());
        $this->assertSame($this->question(1)->options[1]->id, Answer::first()->option_id);

        $mine = $this->getJson('/api/poll', $phone)->json('mine');
        $this->assertEquals([$this->question(1)->id => $this->question(1)->options[1]->id], $mine);
    }

    public function test_recusa_alternativa_de_outra_pergunta_e_aparelho_sem_id(): void
    {
        $this->postJson('/api/answer', [
            'question_id' => $this->question(1)->id,
            'option_id' => $this->question(2)->options[0]->id,
        ], $this->phone())->assertStatus(422);

        $this->postJson('/api/answer', [
            'question_id' => $this->question(1)->id,
            'option_id' => $this->question(1)->options[0]->id,
        ], ['X-Respondent' => 'nao-e-uuid'])->assertStatus(400);

        $this->assertSame(0, Answer::count());
    }

    public function test_enquete_encerrada_recusa_votos(): void
    {
        $this->postJson('/api/admin/accepting', ['accepting' => false], $this->master)->assertOk();

        $this->vote($this->phone(), 1, 0)->assertStatus(423);
        $this->assertFalse($this->getJson('/api/poll')->json('accepting'));
    }

    protected function screen(string $screen, ?int $position = null)
    {
        return $this->postJson('/api/admin/screen', [
            'screen' => $screen,
            'question_id' => $position ? $this->question($position)->id : null,
        ], $this->master)->assertOk();
    }

    public function test_convite_nao_leva_nenhuma_pergunta_ao_telao_nem_ao_celular(): void
    {
        $display = $this->getJson('/api/display');
        $this->assertSame('invite', $display->json('screen'));
        $this->assertSame([], $display->json('questions'));
        $this->assertSame(4, $display->json('total_questions'));

        $poll = $this->getJson('/api/poll', $this->phone());
        $this->assertSame('invite', $poll->json('screen'));
        $this->assertSame([], $poll->json('questions'));
    }

    public function test_selecionar_a_pergunta_leva_so_ela_ao_telao_e_ao_celular_sem_resultado(): void
    {
        $this->vote($this->phone(), 2, 0);
        $this->vote($this->phone(), 2, 1);

        $this->screen('question', 2);

        $display = $this->getJson('/api/display');
        $this->assertSame([2], array_column($display->json('questions'), 'number'));
        $this->assertSame(2, $display->json('questions.0.answers'));
        $this->assertNull($display->json('questions.0.results'));

        $poll = $this->getJson('/api/poll', $this->phone());
        $this->assertSame('question', $poll->json('screen'));
        $this->assertSame([2], array_column($poll->json('questions'), 'number'));
        $this->assertNull($poll->json('questions.0.results'));

        // voto numa pergunta que acabou de sair da tela não se perde
        $this->vote($this->phone(), 1, 0)->assertOk();
    }

    public function test_resultado_so_sai_na_fase_de_resultado(): void
    {
        $this->vote($this->phone(), 1, 0);
        $this->vote($this->phone(), 1, 0);
        $this->vote($this->phone(), 1, 1);

        // o painel vê tudo ao vivo
        $this->assertSame(3, $this->getJson('/api/admin/overview', $this->master)->json('questions.0.results.total'));

        $this->screen('results', 1);

        $display = $this->getJson('/api/display');
        $display->assertJsonPath('screen', 'results')->assertJsonPath('respondents', 3);
        $this->assertSame([67, 33], array_column($display->json('questions.0.results.options'), 'percent'));
        $this->assertNotNull($this->getJson('/api/poll')->json('questions.0.results'));
        $this->assertNotNull($this->question(1)->revealed_at);

        // voltar a perguntar a mesma pergunta esconde o resultado de novo
        $this->screen('question', 1);
        $this->assertNull($this->getJson('/api/display')->json('questions.0.results'));
        $this->assertNull($this->getJson('/api/poll')->json('questions.0.results'));
    }

    public function test_estado_antigo_desconhecido_vale_como_convite(): void
    {
        PollState::current()->update(['screen' => 'all', 'screen_question_id' => null]);

        $this->getJson('/api/display')->assertJsonPath('screen', 'invite')->assertJsonPath('questions', []);
        $this->getJson('/api/poll')->assertJsonPath('screen', 'invite');
    }

    public function test_percentuais_somam_cem(): void
    {
        foreach ([0, 1, 2] as $option) {
            $this->vote($this->phone(), 4, $option);
        }

        $results = $this->getJson('/api/admin/overview', $this->master)->json('questions.3.results.options');

        $this->assertSame(100, array_sum(array_column($results, 'percent')));
    }

    public function test_reset_zera_respostas_e_liberacoes(): void
    {
        $this->vote($this->phone(), 2, 0);
        $this->screen('results', 2);
        $this->postJson('/api/admin/accepting', ['accepting' => false], $this->master);

        $this->postJson('/api/admin/reset', [], $this->master)->assertOk();

        $this->assertSame(0, Answer::count());
        $this->assertSame(0, Question::whereNotNull('revealed_at')->count());
        $state = PollState::current();
        $this->assertTrue($state->accepting);
        $this->assertSame('invite', $state->screen);
    }

    public function test_painel_exige_token(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
        $this->postJson('/api/admin/reset')->assertUnauthorized();
        $this->postJson('/api/admin/screen', ['screen' => 'question'], $this->master)->assertStatus(422);
        $this->postJson('/api/admin/screen', ['screen' => 'all'], $this->master)->assertStatus(422);
    }
}
