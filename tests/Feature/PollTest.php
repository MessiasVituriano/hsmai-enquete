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

    public function test_resultado_so_sai_depois_de_ir_ao_telao(): void
    {
        $this->vote($this->phone(), 1, 0);
        $this->vote($this->phone(), 1, 0);
        $this->vote($this->phone(), 1, 1);

        $this->assertNull($this->getJson('/api/display')->json('questions.0.results'));
        $this->assertNull($this->getJson('/api/poll')->json('questions.0.results'));
        // o painel vê tudo ao vivo
        $this->assertSame(3, $this->getJson('/api/admin/overview', $this->master)->json('questions.0.results.total'));

        $this->postJson('/api/admin/screen', ['screen' => 'question', 'question_id' => $this->question(1)->id], $this->master)
            ->assertOk()
            ->assertJsonPath('screen', 'question');

        $display = $this->getJson('/api/display')->assertOk();
        $display->assertJsonPath('respondents', 3);
        $this->assertSame([67, 33], array_column($display->json('questions.0.results.options'), 'percent'));
        $this->assertNull($display->json('questions.1.results'));
        $this->assertNotNull($this->getJson('/api/poll')->json('questions.0.results'));

        // recolher tira dos celulares e devolve o telão ao convite
        $this->postJson('/api/admin/questions/'.$this->question(1)->id.'/hide', [], $this->master)->assertOk();
        $this->assertNull($this->getJson('/api/poll')->json('questions.0.results'));
        $this->assertSame('invite', PollState::current()->screen);
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
        $this->postJson('/api/admin/screen', ['screen' => 'all'], $this->master)->assertOk();
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
    }
}
