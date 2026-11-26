<?php

namespace Database\Seeders;

use App\Enums\CargoFuncionario;
use App\Enums\StatusBolsa;
use App\Enums\StatusPedido;
use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use App\Models\BolsaSangue;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use App\Models\Pedido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HospitalDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const HOSPITAL = '11222333000181';

    private const OUTRO_HOSPITAL = '98765432000198';

    private const CENTRAL = '0000001';

    private const LESTE = '0000002';

    /**
     * Seed the hospital inventory and orders for the demo credentials.
     */
    public function run(): void
    {
        if (! Hospital::find(self::HOSPITAL)) {
            $this->call(HospitalLoginSeeder::class);
        }

        $this->semearHemocentros();

        $responsavel = Funcionario::where('cnpj_hosp', self::HOSPITAL)
            ->where('tipo_fun', CargoFuncionario::Responsavel->value)
            ->first();

        if (! $responsavel) {
            $this->call(HospitalLoginSeeder::class);

            $responsavel = Funcionario::where('cnpj_hosp', self::HOSPITAL)
                ->where('tipo_fun', CargoFuncionario::Responsavel->value)
                ->firstOrFail();
        }

        // A demonstração é recriada por completo: cada linha representa
        // uma única bolsa e o seeder permanece idempotente.
        BolsaSangue::where('cnpj_hosp', self::HOSPITAL)->delete();
        BolsaSangue::where('cnpj_hosp', self::OUTRO_HOSPITAL)->delete();
        BolsaSangue::whereNull('cnpj_hosp')->delete();
        Pedido::where('cnpj_hosp', self::HOSPITAL)->delete();
        Pedido::where('cnpj_hosp', self::OUTRO_HOSPITAL)->delete();

        $pedidos = $this->semearPedidos($responsavel);

        foreach ($this->linhasDasBolsas($pedidos) as $linha) {
            BolsaSangue::updateOrCreate(['isbt' => $linha['isbt']], $linha);
        }

        foreach ($this->estoqueDosHemocentros() as $linha) {
            BolsaSangue::updateOrCreate(['isbt' => $linha['isbt']], $linha);
        }

        $this->semearBolsasDeOutroHospital();
    }

    private function semearHemocentros(): void
    {
        Hemocentro::updateOrCreate(['cnes_hem' => self::CENTRAL], [
            'nome_hem' => 'Hemocentro Central',
            'cnpj_hem' => '22333444000181',
            'endereco_hem' => 'Rua Central, 100',
            'contato_hem' => '(11) 4000-0001',
            'senha_hem' => 'hemocentro123',
        ]);

        Hemocentro::updateOrCreate(['cnes_hem' => self::LESTE], [
            'nome_hem' => 'Hemocentro Leste',
            'cnpj_hem' => '33444555000192',
            'endereco_hem' => 'Avenida Leste, 200',
            'contato_hem' => '(11) 4000-0002',
            'senha_hem' => 'hemocentro123',
        ]);
    }

    /**
     * @return Collection<int, Pedido>
     */
    private function semearPedidos(Funcionario $responsavel): Collection
    {
        $especificacoes = [
            ['pendente', 'rotina', 2, 'O-', self::CENTRAL, 6, null],
            ['pendente', 'critica', 1, 'AB+', self::LESTE, 5, null],
            ['em_analise', 'urgente', 2, 'A+', self::CENTRAL, 4, null],
            ['em_separacao', 'rotina', 3, 'B+', self::LESTE, 3, null],
            ['em_separacao', 'critica', 2, 'O+', self::CENTRAL, 2, null],
            ['enviado', 'urgente', 3, 'O-', self::CENTRAL, 1, null],
            ['enviado', 'rotina', 2, 'A-', self::LESTE, 1, null],
            ['enviado', 'urgente', 2, 'B-', self::CENTRAL, 1, null],
            ['entregue', 'rotina', 1, 'AB-', self::LESTE, 0, 'hoje'],
            ['entregue', 'urgente', 2, 'O+', self::CENTRAL, 0, 'hoje'],
            ['entregue', 'rotina', 1, 'A+', self::LESTE, 0, 'hoje'],
            ['entregue', 'urgente', 1, 'B+', self::CENTRAL, 2, 'antigo'],
        ];

        $pedidos = collect();

        foreach ($especificacoes as $indice => $especificacao) {
            [$status, $urgencia, $quantidade, $tipo, $cnes, $dias, $entrega] = $especificacao;
            $idPed = 9001 + $indice;
            $marcador = sprintf('[DEMO-%02d]', $indice + 1);
            $pedido = new Pedido;
            $pedido->id_ped = $idPed;
            $pedido->fill([
                'cnpj_hosp' => self::HOSPITAL,
                'cnes_hem' => $cnes,
                'cod_reg_fun' => $responsavel->cod_reg_fun,
                'desc_ped' => $marcador.' Paciente / '.$this->setores()[$indice % 8],
                'data_hora_ped' => now()->subDays($dias),
                'status_ped' => $status,
                'quant_ped' => $quantidade,
                'url_requisicao_transfusao_ped' => 'requisicoes/'.self::HOSPITAL.'/'.$idPed.'.pdf',
                'tipo_sanguineo_ped' => $tipo,
                'urgencia_ped' => $urgencia,
                'data_hora_envio_ped' => $status === StatusPedido::Enviado->value
                    ? now()->subHours(2)
                    : null,
                'data_hora_entrega_ped' => $status === StatusPedido::Entregue->value
                    ? ($entrega === 'antigo' ? now()->subDays(2) : now()->setTime(8 + $indice - 8, 0))
                    : null,
            ]);
            $pedido->save();

            Storage::disk('local')->put(
                $pedido->url_requisicao_transfusao_ped,
                $this->pdf($marcador.' Pedido '.$idPed),
            );

            $pedidos->push($pedido);
        }

        return $pedidos;
    }

    /**
     * @return list<string>
     */
    private function setores(): array
    {
        return [
            'UTI', 'Emergência', 'Centro Cirúrgico', 'Pediatria', 'Clínica Médica',
            'Obstetrícia', 'Ortopedia', 'Neurologia',
        ];
    }

    /**
     * @param  Collection<int, Pedido>  $pedidos
     * @return Collection<int, array<string, mixed>>
     */
    private function linhasDasBolsas(Collection $pedidos): Collection
    {
        $linhas = collect();
        $serial = 1;
        $tipos = TipoSanguineo::cases();

        // 41 bolsas disponíveis não vencidas. A distribuição preserva
        // tipos críticos, intermediários e saudáveis dentro das metas.
        $vencendoPorTipo = [
            'O-' => [1, 2],
            'A-' => [3],
            'AB+' => [5],
            'B-' => [2],
            'AB-' => [1],
        ];

        foreach ([
            ['O-', 15], ['A-', 10], ['AB+', 13], ['B-', 2], ['AB-', 1],
        ] as [$tipo, $total]) {
            foreach (range(1, $total) as $indice) {
                $diasVencimento = in_array($indice, $vencendoPorTipo[$tipo] ?? [], true)
                    ? $indice
                    : 30;

                $linhas->push($this->linha($serial++, $tipo, $diasVencimento, StatusBolsa::Disponivel));
            }
        }

        // Vencidas continuam no inventário, mas não entram nos cards.
        foreach (range(0, 4) as $indice) {
            $linhas->push($this->linha(
                $serial++,
                $tipos[$indice]->value,
                -1 - $indice,
                StatusBolsa::Disponivel,
            ));
        }

        // Seis bolsas a caminho, todas ligadas aos pedidos enviados.
        foreach ([
            [$pedidos[5], 3],
            [$pedidos[6], 2],
            [$pedidos[7], 1],
        ] as [$pedido, $quantidade]) {
            foreach (range(1, $quantidade) as $indice) {
                $linhas->push($this->linha(
                    $serial++,
                    $pedido->tipo_sanguineo_ped->value,
                    30,
                    StatusBolsa::EmTransito,
                    $pedido,
                    'Paciente / Envio '.$indice,
                    2,
                ));
            }
        }

        // A reserva do pedido parcial já foi recebida, mas ainda não
        // completou a quantidade solicitada.
        foreach (range(0, 7) as $indice) {
            $pedido = $indice === 0
                ? $pedidos[7]
                : $pedidos[2 + ($indice % 3)];

            $linhas->push($this->linha(
                $serial++,
                $pedido->tipo_sanguineo_ped->value,
                30,
                StatusBolsa::Reservada,
                $pedido,
                'Paciente / Reserva '.($indice + 1),
                2 + $indice,
            ));
        }

        foreach (range(0, 5) as $indice) {
            $linhas->push($this->linha(
                $serial++,
                $tipos[($indice + 4) % 8]->value,
                30,
                StatusBolsa::Utilizada,
                $pedidos[8 + ($indice % 4)],
                'Paciente / UTI '.($indice + 1),
                4 + $indice,
                1 + $indice,
            ));
        }

        foreach (range(0, 3) as $indice) {
            $linhas->push($this->linha(
                $serial++,
                $tipos[($indice + 5) % 8]->value,
                30,
                StatusBolsa::Descartada,
                null,
                null,
                null,
                2 + $indice,
            ));
        }

        return $linhas;
    }

    /**
     * @return array<string, mixed>
     */
    private function linha(
        int $serial,
        string $tipo,
        int $diasVencimento,
        StatusBolsa $status,
        ?Pedido $pedido = null,
        ?string $paciente = null,
        ?int $horasEnvio = null,
        ?int $horasSaida = null,
        ?string $cnpjHosp = null,
    ): array {
        $envio = $horasEnvio === null ? null : now()->subHours($horasEnvio);
        $recebimento = $envio === null || $status === StatusBolsa::EmTransito
            ? null
            : $envio->copy()->addHour();

        return [
            'isbt' => $this->isbt($serial),
            'cnes_hem' => $serial % 2 === 0 ? self::CENTRAL : self::LESTE,
            'quant_bolsa' => 1,
            'tipo_bolsa' => $tipo,
            'disponibilidade_bolsa' => $status->value,
            'data_venc_bolsa' => today()->addDays($diasVencimento)->toDateString(),
            'cnpj_hosp' => $cnpjHosp ?? self::HOSPITAL,
            'id_ped' => $pedido?->id_ped,
            'data_hora_envio_bolsa' => $envio?->toDateTimeString(),
            'data_hora_recebimento_bolsa' => $recebimento?->toDateTimeString(),
            'data_hora_saida_bolsa' => $horasSaida === null ? null : now()->subHours($horasSaida)->toDateTimeString(),
            'paciente_destino_bolsa' => $paciente,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function estoqueDosHemocentros(): Collection
    {
        $distribuicao = [
            ['O-', self::CENTRAL], ['O-', self::CENTRAL], ['O-', self::LESTE], ['O-', self::LESTE],
            ['O+', self::CENTRAL], ['O+', self::CENTRAL], ['O+', self::LESTE],
            ['A-', self::CENTRAL], ['A-', self::LESTE], ['A-', self::LESTE],
            ['A+', self::CENTRAL], ['A+', self::LESTE],
            ['B-', self::CENTRAL], ['B-', self::LESTE],
            ['B+', self::CENTRAL], ['B+', self::LESTE],
            ['AB-', self::CENTRAL], ['AB-', self::LESTE],
            ['AB+', self::CENTRAL], ['AB+', self::LESTE],
        ];

        return collect($distribuicao)->map(function (array $item, int $indice): array {
            [$tipo, $cnes] = $item;
            $serial = $indice + 1;

            return [
                'isbt' => sprintf('H000026%06d', $serial),
                'cnes_hem' => $cnes,
                'quant_bolsa' => 1,
                'tipo_bolsa' => $tipo,
                'disponibilidade_bolsa' => StatusBolsa::Disponivel->value,
                'data_venc_bolsa' => today()->addDays(30)->toDateString(),
                'cnpj_hosp' => null,
                'id_ped' => null,
                'data_hora_envio_bolsa' => null,
                'data_hora_recebimento_bolsa' => null,
                'data_hora_saida_bolsa' => null,
                'paciente_destino_bolsa' => null,
            ];
        });
    }

    private function semearBolsasDeOutroHospital(): void
    {
        $tipos = TipoSanguineo::cases();
        $pedido = new Pedido;
        $pedido->id_ped = 9101;
        $pedido->fill([
            'cnpj_hosp' => self::OUTRO_HOSPITAL,
            'cnes_hem' => self::CENTRAL,
            'cod_reg_fun' => null,
            'desc_ped' => '[DEMO-OUTRO] Pedido do Hospital Municipal',
            'data_hora_ped' => now()->subDay(),
            'status_ped' => StatusPedido::Pendente->value,
            'quant_ped' => 1,
            'url_requisicao_transfusao_ped' => null,
            'tipo_sanguineo_ped' => 'O+',
            'urgencia_ped' => UrgenciaPedido::Rotina->value,
        ]);
        $pedido->save();

        foreach (range(101, 103) as $indice => $serial) {
            $dados = $this->linha(
                $serial,
                $tipos[$indice]->value,
                30,
                StatusBolsa::Disponivel,
                null,
                null,
                null,
                null,
                self::OUTRO_HOSPITAL,
            );

            BolsaSangue::updateOrCreate(['isbt' => $dados['isbt']], $dados);
        }
    }

    private function isbt(int $serial): string
    {
        return sprintf('W0000%02d%06d', (int) now()->format('y'), $serial);
    }

    private function pdf(string $titulo): string
    {
        $conteudo = 'BT /F1 12 Tf 72 760 Td ('.$titulo.') Tj ET';
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R '
                .'/Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($conteudo)." >>\nstream\n".$conteudo."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $deslocamentos = [];

        foreach ($objetos as $indice => $objeto) {
            $deslocamentos[] = strlen($pdf);
            $pdf .= ($indice + 1)." 0 obj\n".$objeto."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($deslocamentos) + 1)."\n0000000000 65535 f \n";

        foreach ($deslocamentos as $deslocamento) {
            $pdf .= sprintf("%010d 00000 n \n", $deslocamento);
        }

        return $pdf.'trailer << /Size '.(count($deslocamentos) + 1).' /Root 1 0 R >>'
            ."\nstartxref\n".$xref."\n%%EOF\n";
    }
}
