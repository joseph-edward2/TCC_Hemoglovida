<?php

namespace App\Http\Requests;

use App\Enums\StatusBolsa;
use App\Models\BolsaSangue;
use App\Models\Hospital;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class AtualizarBolsaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $hospital = Hospital::daSessao();
        $bolsa = $this->route('bolsa');

        return $hospital !== null
            && $bolsa instanceof BolsaSangue
            && $bolsa->cnpj_hosp === $hospital->cnpj_hosp;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'disponibilidade_bolsa' => ['required', new Enum(StatusBolsa::class)],
            'paciente_destino_bolsa' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disponibilidade_bolsa.required' => 'Informe o status da bolsa.',
            'disponibilidade_bolsa.enum' => 'Status da bolsa inválido.',
            'paciente_destino_bolsa.required' => 'Informe o paciente de destino.',
            'paciente_destino_bolsa.string' => 'O paciente de destino deve ser um texto.',
            'paciente_destino_bolsa.max' => 'O paciente de destino deve ter no máximo 150 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes(
            'paciente_destino_bolsa',
            ['required', 'string', 'max:150'],
            fn (object $input): bool => StatusBolsa::tryFrom($input->disponibilidade_bolsa ?? '')?->requerPaciente() === true,
        );

        $validator->after(function (Validator $validator): void {
            $bolsa = $this->route('bolsa');
            $destino = StatusBolsa::tryFrom($this->input('disponibilidade_bolsa', ''));

            if (! $bolsa instanceof BolsaSangue || $destino === null) {
                return;
            }

            $atual = $bolsa->disponibilidade_bolsa;

            if (! $atual->podeTransicionarPara($destino, (bool) $bolsa->vencida)) {
                $validator->errors()->add(
                    'disponibilidade_bolsa',
                    $atual->mensagemTransicaoInvalida($destino, (bool) $bolsa->vencida),
                );
            }
        });
    }
}
