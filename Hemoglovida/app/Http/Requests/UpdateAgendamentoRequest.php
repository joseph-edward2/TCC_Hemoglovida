<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendamentoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cpf_doador' => ['required', 'string', 'regex:/^\d{11}$/', Rule::exists('doador', 'cpf_doador')],
            'id_evento' => ['sometimes', 'required_with:cnes_hem', 'integer', Rule::exists('evento', 'id_evento')],
            'cnes_hem' => ['sometimes', 'required_with:id_evento', 'string', 'size:7', Rule::exists('hemocentro', 'cnes_hem')],
            'data_hora_agendamento' => ['sometimes', 'date', 'after:now'],
        ];
    }
}
