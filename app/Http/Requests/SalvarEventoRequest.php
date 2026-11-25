<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarEventoRequest extends FormRequest
{
    public const TIPOS_SANGUINEOS = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];

    public const URGENCIAS = ['Baixa', 'Média', 'Alta', 'Crítica'];

    public const PUBLICOS = ['Todos os doadores', 'Doadores frequentes', 'Novos doadores'];

    public const TIMEZONE = 'America/Sao_Paulo';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'imagem' => $this->route('categoria') === 'campanha'
                ? ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048']
                : ['prohibited'],
            'titulo' => ['required', 'string', 'max:255'],
            'local' => ['required', 'string', 'max:255'],
            'mensagem' => ['nullable', 'string', 'max:10000'],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'expiracao' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'tipos' => ['sometimes', 'list', 'max:8'],
            'tipos.*' => ['required', 'string', 'distinct', Rule::in(self::TIPOS_SANGUINEOS)],
            'urgencia' => ['required', 'string', Rule::in(self::URGENCIAS)],
            'publico' => ['required', 'string', Rule::in(self::publicos($this->route('categoria')))],
            'status' => ['required', 'string', 'in:publicado,rascunho,pausado'],
        ];
    }

    /** @return list<string> */
    public static function publicos(string $categoria): array
    {
        return $categoria === 'alerta' ? [...self::PUBLICOS, 'Doadores O-'] : self::PUBLICOS;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'imagem.image' => 'Escolha uma imagem JPG, PNG ou WebP.',
            'imagem.mimes' => 'Escolha uma imagem JPG, PNG ou WebP.',
            'imagem.max' => 'A imagem deve ter até 2 MB.',
            'titulo.required' => 'Informe o título do evento.',
            'local.required' => 'Informe o local do evento.',
            'inicio.required' => 'Informe a data de início.',
            'inicio.date_format' => 'Informe uma data de início válida.',
            'expiracao.date_format' => 'Informe uma data de expiração válida.',
            'expiracao.after_or_equal' => 'A expiração deve ser igual ou posterior ao início.',
            'tipos.*.in' => 'Selecione um tipo sanguíneo válido.',
            'tipos.*.distinct' => 'Selecione cada tipo sanguíneo apenas uma vez.',
            'urgencia.in' => 'Selecione um nível de urgência válido.',
            'publico.in' => 'Selecione um público-alvo válido.',
        ];
    }

    /** @return array{titulo_evento: string, local_evento: string, desc_evento: ?string, data_hora_evento: CarbonImmutable, expiracao_evento: ?string, tipos_sanguineos: string, urgencia: string, publico_alvo: string, status_evento: string} */
    public function dadosEvento(?string $inicioAnterior = null): array
    {
        $inicio = CarbonImmutable::parse($this->validated('inicio'), self::TIMEZONE);
        if ($inicioAnterior !== null) {
            $inicio = $inicio->setTimeFrom(CarbonImmutable::parse($inicioAnterior, 'UTC')->setTimezone(self::TIMEZONE));
        }

        return [
            'titulo_evento' => $this->validated('titulo'),
            'local_evento' => $this->validated('local'),
            'desc_evento' => $this->validated('mensagem'),
            'data_hora_evento' => $inicio->utc(),
            'expiracao_evento' => $this->validated('expiracao'),
            'tipos_sanguineos' => json_encode($this->validated('tipos', []), JSON_THROW_ON_ERROR),
            'urgencia' => $this->validated('urgencia'),
            'publico_alvo' => $this->validated('publico'),
            'status_evento' => $this->validated('status'),
        ];
    }
}
