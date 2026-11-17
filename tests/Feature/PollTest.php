<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\PollState;
use App\Models\Question;
use App\Models\Respondent;
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

    protected int $people = 0;

    /** Um celular já cadastrado (é o caminho normal: ninguém responde sem cadastro). */
    protected function phone(array $profile = []): array
    {
        $headers = ['X-Respondent' => (string) Str::uuid()];
        $n = ++$this->people;

        $this->postJson('/api/register', [
            'name' => "Pessoa {$n}",
            'email' => "pessoa{$n}@hotel.com",
            'hotel' => 'Hotel Aurora',
            ...$profile,
        ], $headers)->assertCreated();

        return $headers;
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

        // aparelho sem cadastro não responde
        $this->postJson('/api/answer', [
            'question_id' => $this->question(1)->id,
            'option_id' => $this->question(1)->options[0]->id,
        ], ['X-Respondent' => (string) Str::uuid()])->assertStatus(403);

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
        $this->assertSame(0, Respondent::count());
        $this->assertSame(0, Question::whereNotNull('revealed_at')->count());
        $state = PollState::current();
        $this->assertTrue($state->accepting);
        $this->assertSame('invite', $state->screen);
    }

    public function test_cadastro_com_email_ou_whatsapp_e_hotel(): void
    {
        $device = ['X-Respondent' => (string) Str::uuid()];

        $this->postJson('/api/register', ['name' => 'Ana', 'hotel' => 'Hotel Sol'], $device)
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
        $this->postJson('/api/register', ['name' => 'Ana', 'phone' => '1199', 'hotel' => 'Hotel Sol'], $device)
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/register', ['name' => 'Ana', 'email' => 'ana@x.com'], $device)
            ->assertStatus(422)->assertJsonValidationErrors('hotel');

        $this->postJson('/api/register', ['name' => ' Ana ', 'phone' => '+55 (11) 98888-7777', 'hotel' => 'Hotel Sol'], $device)
            ->assertCreated()
            ->assertJsonPath('me.phone', '11988887777')
            ->assertJsonPath('me.name', 'Ana');

        $this->getJson('/api/poll', $device)->assertJsonPath('me.hotel', 'Hotel Sol');
        $this->getJson('/api/poll', ['X-Respondent' => (string) Str::uuid()])->assertJsonPath('me', null);

        // corrigir o próprio cadastro troca o contato; não cria outra pessoa
        $this->postJson('/api/register', ['name' => 'Ana Souza', 'email' => 'ANA@x.com', 'hotel' => 'Hotel Sol'], $device)
            ->assertOk()
            ->assertJsonPath('me.email', 'ana@x.com')
            ->assertJsonPath('me.phone', null);
        $this->assertSame(1, Respondent::count());
    }

    public function test_mesmo_contato_em_outro_aparelho_adota_o_cadastro_e_as_respostas(): void
    {
        $first = $this->phone(['email' => 'joao@hotel.com']);
        $this->vote($first, 1, 0)->assertOk();

        $newDevice = ['X-Respondent' => (string) Str::uuid()];
        $token = $this->postJson('/api/register', ['name' => 'João', 'email' => 'joao@hotel.com', 'hotel' => 'Hotel Mar'], $newDevice)
            ->assertCreated()->json('token');

        $this->assertSame($first['X-Respondent'], $token);
        $this->assertSame(1, Respondent::count());
        $this->assertSame('Hotel Mar', Respondent::first()->hotel);
        $this->assertCount(1, (array) $this->getJson('/api/poll', ['X-Respondent' => $token])->json('mine'));
    }

    public function test_quem_ja_tem_cadastro_nao_toma_o_contato_de_outro(): void
    {
        $this->phone(['email' => 'maria@hotel.com']);
        $other = $this->phone(['email' => 'pedro@hotel.com']);

        $this->postJson('/api/register', ['name' => 'Pedro', 'email' => 'maria@hotel.com', 'hotel' => 'X'], $other)
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_painel_lista_e_exporta_os_cadastros_com_as_respostas(): void
    {
        $ana = $this->phone(['name' => 'Ana', 'email' => null, 'phone' => '11988887777', 'hotel' => 'Hotel Sol']);
        $this->vote($ana, 4, 2);

        $this->getJson('/api/admin/overview', $this->master)->assertJsonPath('registered', 1);

        $list = $this->getJson('/api/admin/respondents', $this->master)->assertOk();
        $list->assertJsonPath('respondents.0.name', 'Ana')
            ->assertJsonPath('respondents.0.phone', '(11) 98888-7777')
            ->assertJsonPath('respondents.0.answers', [null, null, null, 'Volume de pernoites']);

        $csv = $this->get('/api/admin/respondents.csv', $this->master)->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}Nome;E-mail;WhatsApp;Hotel;", $csv);
        $this->assertStringContainsString('Ana;;"(11) 98888-7777";"Hotel Sol";', $csv);
        $this->assertStringContainsString('"Volume de pernoites"', $csv);

        $this->get('/api/admin/respondents.csv')->assertUnauthorized();
        $this->getJson('/api/admin/respondents')->assertUnauthorized();
    }

    public function test_painel_exige_token(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
        $this->postJson('/api/admin/reset')->assertUnauthorized();
        $this->postJson('/api/admin/screen', ['screen' => 'question'], $this->master)->assertStatus(422);
        $this->postJson('/api/admin/screen', ['screen' => 'all'], $this->master)->assertStatus(422);
    }
}
