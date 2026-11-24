<?php

namespace App\Http\Requests;

use App\Enums\CargoFuncionario;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

abstract class StoreFuncionarioRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf_funcionario' => Cpf::normalizar($this->input('cpf_funcionario')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nome_funcionario' => ['required', 'string', 'max:255'],
            'cpf_funcionario' => [
                'required',
                'string',
                new Cpf,
                Rule::unique('funcionario', 'cpf_fun'),
            ],
            'senha_funcionario' => ['required', 'string', 'min:8', 'max:255'],
            'tipo_funcionario' => ['required', new Enum(CargoFuncionario::class)],
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
            'cpf_funcionario.required' => 'Informe o CPF do funcionário.',
            'cpf_funcionario.unique' => 'Já existe um funcionário com este CPF.',
            'senha_funcionario.required' => 'Informe a senha do funcionário.',
            'senha_funcionario.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'tipo_funcionario.required' => 'Selecione o cargo do funcionário.',
        ];
    }

    /**
     * Dados prontos para gravar no model Funcionario.
     *
     * @return array<string, string>
     */
    public function dadosFuncionario(): array
    {
        $validated = $this->validated();

        return [
            'nome_fun' => $validated['nome_funcionario'],
            'cpf_fun' => $validated['cpf_funcionario'],
            'senha_fun' => $validated['senha_funcionario'],
            'tipo_fun' => $validated['tipo_funcionario'],
        ];
    }
}
