<?php

namespace App\Http\Requests;

use App\Enums\CargoFuncionario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

abstract class UpdateFuncionarioRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nome_funcionario' => ['required', 'string', 'max:255'],
            'tipo_funcionario' => ['required', new Enum(CargoFuncionario::class)],
            'senha_funcionario' => [
                'nullable',
                'string',
                'min:8',
                'max:255',
                Rule::when(filled($this->input('senha_funcionario')), ['confirmed']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome_funcionario.required' => 'Informe o nome do funcionário.',
            'tipo_funcionario.required' => 'Selecione o cargo do funcionário.',
            'senha_funcionario.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'senha_funcionario.confirmed' => 'A confirmação da senha não corresponde.',
        ];
    }

    /**
     * Dados que podem ser atualizados no model Funcionario.
     *
     * @return array<string, string>
     */
    public function dadosAtualizacao(): array
    {
        $validated = $this->validated();

        $dados = [
            'nome_fun' => $validated['nome_funcionario'],
            'tipo_fun' => $validated['tipo_funcionario'],
        ];

        if (filled($validated['senha_funcionario'] ?? null)) {
            $dados['senha_fun'] = $validated['senha_funcionario'];
        }

        return $dados;
    }
}
