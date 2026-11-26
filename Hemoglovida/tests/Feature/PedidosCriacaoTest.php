<?php

use App\Enums\CargoFuncionario;
use App\Enums\StatusPedido;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-25 12:00:00');
    Storage::fake('local');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dadosDoPedido(string $cnesHem, ?UploadedFile $arquivo = null): array
{
    $dados = [
        'tipo_sanguineo' => 'O-',
        'quantidade' => 3,
        'urgencia' => 'urgente',
        'paciente_setor' => 'Maria S. - UTI',
        'cnes_hem' => $cnesHem,
    ];

    if ($arquivo !== null) {
        $dados['requisicao'] = $arquivo;
    }

    return $dados;
}

function pdfFake(string $nome = 'requisicao.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
}

test('a conta do hospital cria um pedido pendente com pdf no disco privado', function () {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create(['nome_hem' => 'Hemocentro Central']);

    $resposta = $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), dadosDoPedido($hemocentro->cnes_hem, pdfFake()))
        ->assertCreated();

    $pedido = $hospital->pedidos()->firstOrFail();

    expect($pedido->status_ped)->toBe(StatusPedido::Pendente)
        ->and($pedido->cod_reg_fun)->toBeNull()
        ->and($pedido->quant_ped)->toBe(3)
        ->and($pedido->desc_ped)->toBe('Maria S. - UTI')
        ->and($pedido->data_hora_ped->toISOString())->toBe(now()->toISOString())
        ->and($pedido->url_requisicao_transfusao_ped)->toStartWith('requisicoes/'.$hospital->cnpj_hosp.'/')
        ->and(Storage::disk('local')->exists($pedido->url_requisicao_transfusao_ped))->toBeTrue()
        ->and($resposta->json('data.status_ped'))->toBe('pendente');
});

test('o funcionário logado fica registrado como responsável pelo pedido', function () {
    $hospital = Hospital::factory()->create();
    $funcionario = $hospital->funcionarios()->create([
        'nome_fun' => 'Renato',
        'senha_fun' => 'senha',
        'tipo_fun' => CargoFuncionario::Responsavel->value,
    ]);
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($funcionario, 'funcionario')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), dadosDoPedido($hemocentro->cnes_hem, pdfFake()))
        ->assertCreated();

    expect($hospital->pedidos()->firstOrFail()->cod_reg_fun)->toBe($funcionario->cod_reg_fun);
});

test('o cnpj enviado pelo cliente é ignorado e o tipo ab+ é aceito', function () {
    $hospital = Hospital::factory()->create();
    $outroHospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $dados = dadosDoPedido($hemocentro->cnes_hem, pdfFake());
    $dados['tipo_sanguineo'] = 'AB+';
    $dados['cnpj_hosp'] = $outroHospital->cnpj_hosp;

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), $dados)
        ->assertCreated()
        ->assertJsonPath('data.tipo_sanguineo_ped', 'AB+');

    expect($hospital->pedidos()->count())->toBe(1)
        ->and($outroHospital->pedidos()->count())->toBe(0);
});

test('a criação rejeita campos obrigatórios e inválidos', function (array $dados, string $campo, string $mensagem) {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), array_merge(
            dadosDoPedido($hemocentro->cnes_hem, pdfFake()),
            $dados,
        ))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo => $mensagem]);

    expect($hospital->pedidos()->count())->toBe(0);
})->with([
    'tipo inválido' => [['tipo_sanguineo' => 'XO'], 'tipo_sanguineo', 'Tipo sanguíneo inválido.'],
    'quantidade zero' => [['quantidade' => 0], 'quantidade', 'A quantidade deve ser de pelo menos 1 bolsa.'],
    'quantidade 51' => [['quantidade' => 51], 'quantidade', 'A quantidade não pode passar de 50 bolsas.'],
    'urgência inválida' => [['urgencia' => 'inexistente'], 'urgencia', 'Urgência inválida.'],
    'paciente vazio' => [['paciente_setor' => ''], 'paciente_setor', 'Informe o paciente ou setor.'],
    'hemocentro inexistente' => [['cnes_hem' => '9999999'], 'cnes_hem', 'Hemocentro inexistente.'],
]);

test('o documento é obrigatório, precisa ser pdf verdadeiro e ter no máximo 5 MB', function (array $substituicao, string $campo, string $mensagem) {
    $hospital = Hospital::factory()->create();
    $hemocentro = Hemocentro::factory()->create();
    $dados = dadosDoPedido($hemocentro->cnes_hem, pdfFake());

    if (array_key_exists('remover', $substituicao)) {
        unset($dados['requisicao']);
    } else {
        $dados['requisicao'] = $substituicao['arquivo'];
    }

    $this->actingAs($hospital, 'hospital')
        ->withHeader('Accept', 'application/json')
        ->post(route('hospital.pedidos.store'), $dados)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo => $mensagem]);
})->with([
    'sem arquivo' => [['remover' => true], 'requisicao', 'Envie o PDF assinado da requisição.'],
    'texto com extensão pdf' => [
        ['arquivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'não é um pdf')],
        'requisicao',
        'O documento deve ser um PDF válido.',
    ],
    'acima de 5 MB' => [
        ['arquivo' => UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf')],
        'requisicao',
        'O PDF não pode passar de 5 MB.',
    ],
]);
