<?php

namespace App\Http\Controllers;

use App\DocumentoRequisicao;
use App\ResumoPedidos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class HospitalPedidoController extends Controller
{
    public function index(Request $request): View
    {
        $pedidos = DB::table('pedido')->where('cnpj_hosp', $request->user()->cnpj_hosp);

        return view('hospital.Hospital_pedidos', [
            'totais' => ResumoPedidos::totais($pedidos, recebimentos: true),
            'pedidos' => $pedidos->orderByDesc('data_hora_ped')->orderByDesc('id_ped')
                ->paginate(5, ['id_ped', 'data_hora_ped', 'cnes_hem', 'tipo_sanguineo_ped', 'quant_ped', 'urgencia_ped', 'status_ped', 'url_requisicao_transfusao_ped'])
                ->fragment('acompanhamento')
                ->through(function (stdClass $pedido): stdClass {
                    $pedido->situacao = ResumoPedidos::STATUS[$pedido->status_ped] ?? Str::ucfirst(str_replace('_', ' ', $pedido->status_ped));
                    $pedido->requisicao = DocumentoRequisicao::url($pedido->url_requisicao_transfusao_ped, $pedido->id_ped, 'hospital.pedidos.requisicao');

                    return $pedido;
                }),
            'etapas' => ResumoPedidos::ETAPAS,
            'urgencias' => ['critica' => 'Crítica', 'urgente' => 'Urgente', 'rotina' => 'Rotina'],
            'tiposSanguineos' => ResumoPedidos::TIPOS_SANGUINEOS,
            'hemocentros' => DB::table('hemocentro')->orderBy('cnes_hem')->get(['cnes_hem', 'endereco_hem']),
        ]);
    }

    public function requisicao(Request $request, int $pedido): StreamedResponse
    {
        $arquivo = DB::table('pedido')->where('id_ped', $pedido)->where('cnpj_hosp', $request->user()->cnpj_hosp)
            ->value('url_requisicao_transfusao_ped');

        return DocumentoRequisicao::exibir($arquivo, $pedido);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'tipo_sanguineo' => ['required', 'string', Rule::in(ResumoPedidos::TIPOS_SANGUINEOS)],
            'quantidade' => ['required', 'integer', 'between:1,2147483647'],
            'urgencia' => ['required', 'string', Rule::in(['rotina', 'urgente', 'critica'])],
            'paciente_setor' => ['nullable', 'string', 'max:255'],
            'hemocentro' => ['bail', 'required', 'string', 'size:7', 'exists:hemocentro,cnes_hem'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'tipo_sanguineo.*' => 'Selecione um tipo sanguíneo válido.',
            'quantidade.*' => 'Informe uma quantidade válida de bolsas (inteiro maior que zero).',
            'urgencia.*' => 'Selecione uma urgência válida.',
            'paciente_setor.*' => 'Informe o paciente ou setor em até 255 caracteres.',
            'hemocentro.*' => 'Selecione um hemocentro cadastrado.',
            'documento.*' => 'Envie a requisição em PDF de até 10 MB.',
        ]);

        $arquivo = $request->file('documento')?->store('requisicoes-pedido', 'local');
        abort_if($arquivo === false, 500, 'Não foi possível salvar o documento.');

        try {
            $pedido = DB::table('pedido')->insertGetId([
                'cnpj_hosp' => $request->user()->cnpj_hosp,
                'cnes_hem' => $dados['hemocentro'],
                'tipo_sanguineo_ped' => $dados['tipo_sanguineo'],
                'desc_ped' => 'Pedido de bolsas '.$dados['tipo_sanguineo'],
                'quant_ped' => $dados['quantidade'],
                'urgencia_ped' => $dados['urgencia'],
                'paciente_setor_ped' => $dados['paciente_setor'] ?? null,
                'url_requisicao_transfusao_ped' => $arquivo,
                'data_hora_ped' => now('UTC'),
            ], 'id_ped');
        } catch (Throwable $exception) {
            if ($arquivo !== null) {
                Storage::disk('local')->delete($arquivo);
            }
            throw $exception;
        }

        return to_route('hospital.pedidos')->with('status', "Requisição #{$pedido} enviada com sucesso.");
    }
}
