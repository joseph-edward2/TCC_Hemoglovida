<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalvarAgendaRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $time = ['required', 'date_format:H:i'];
        $capacity = ['required', 'integer:strict', 'between:0,2147483647'];
        $toggle = ['required', 'boolean:strict'];

        return [
            'revision' => ['required', 'integer:strict', 'min:0'],
            'pattern' => ['required', 'list', 'size:7'],
            'pattern.*' => ['required', 'array:open,start,end,cap'],
            'pattern.*.open' => $toggle,
            'pattern.*.start' => $time,
            'pattern.*.end' => $time,
            'pattern.*.cap' => $capacity,
            'blocked' => ['present', 'list'],
            'blocked.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'overrides' => ['present', 'list'],
            'overrides.*' => ['required', 'array:date,open,horarios'],
            'overrides.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'overrides.*.open' => $toggle,
            'overrides.*.horarios' => ['present', 'list'],
            'overrides.*.horarios.*' => ['required', 'array:start,end,vagas,active'],
            'overrides.*.horarios.*.start' => $time,
            'overrides.*.horarios.*.end' => $time,
            'overrides.*.horarios.*.vagas' => $capacity,
            'overrides.*.horarios.*.active' => $toggle,
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('pattern') as $index => $day) {
                if ($day['open'] && $day['end'] <= $day['start']) {
                    $validator->errors()->add("pattern.$index.end", 'O fim do expediente deve ser posterior ao início.');
                }
            }

            foreach ($this->input('overrides') as $index => $day) {
                $previousEnd = null;
                foreach ($day['horarios'] as $slot) {
                    if ($slot['end'] <= $slot['start'] || $slot['start'] < $previousEnd) {
                        $validator->errors()->add("overrides.$index.horarios", "Confira a ordem e a duração dos horários de {$day['date']}.");
                        break;
                    }
                    $previousEnd = $slot['end'];
                }
            }
        }];
    }
}
