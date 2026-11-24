<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendamentoRequest extends FormRequest
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
            'id_evento' => ['required', 'integer', Rule::exists('evento', 'id_evento')],
            'cnes_hem' => ['required', 'string', 'size:7', Rule::exists('hemocentro', 'cnes_hem')],
            'data_hora_agendamento' => ['required', 'date', 'after:now'],
        ];
    }
}
