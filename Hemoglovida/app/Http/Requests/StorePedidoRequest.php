<?php

namespace App\Http\Requests;

use App\Enums\TipoSanguineo;
use App\Enums\UrgenciaPedido;
use App\Models\Hospital;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePedidoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Hospital::daSessao() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo_sanguineo' => ['required', new Enum(TipoSanguineo::class)],
            'quantidade' => ['required', 'integer', 'min:1', 'max:50'],
            'urgencia' => ['required', new Enum(UrgenciaPedido::class)],
            'paciente_setor' => ['required', 'string', 'max:255'],
            'cnes_hem' => ['required', 'exists:hemocentro,cnes_hem'],
            'requisicao' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_sanguineo.required' => 'Selecione o tipo sanguíneo requerido.',
            'tipo_sanguineo.enum' => 'Tipo sanguíneo inválido.',
            'quantidade.required' => 'Informe a quantidade de bolsas.',
            'quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'quantidade.min' => 'A quantidade deve ser de pelo menos 1 bolsa.',
            'quantidade.max' => 'A quantidade não pode passar de 50 bolsas.',
            'urgencia.required' => 'Informe a urgência do pedido.',
            'urgencia.enum' => 'Urgência inválida.',
            'paciente_setor.required' => 'Informe o paciente ou setor.',
            'paciente_setor.string' => 'O paciente ou setor deve ser um texto.',
            'paciente_setor.max' => 'O paciente ou setor deve ter no máximo 255 caracteres.',
            'cnes_hem.required' => 'Selecione o hemocentro de destino.',
            'cnes_hem.exists' => 'Hemocentro inexistente.',
            'requisicao.required' => 'Envie o PDF assinado da requisição.',
            'requisicao.file' => 'Envie o PDF assinado da requisição.',
            'requisicao.mimes' => 'O documento deve ser um PDF válido.',
            'requisicao.mimetypes' => 'O documento deve ser um PDF válido.',
            'requisicao.max' => 'O PDF não pode passar de 5 MB.',
        ];
    }

    /**
     * Confere a assinatura do arquivo, que os validadores de extensão e MIME
     * não garantem sozinhos.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $arquivo = $this->file('requisicao');

            if ($arquivo === null) {
                return;
            }

            $recurso = fopen($arquivo->getRealPath(), 'rb');

            if ($recurso === false) {
                $validator->errors()->add('requisicao', 'O documento deve ser um PDF válido.');

                return;
            }

            $assinatura = fread($recurso, 5);
            fclose($recurso);

            if ($assinatura !== '%PDF-') {
                $validator->errors()->add('requisicao', 'O documento deve ser um PDF válido.');
            }
        });
    }
}
