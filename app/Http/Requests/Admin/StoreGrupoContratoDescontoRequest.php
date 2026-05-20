<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrupoContratoDescontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'competencia' => [
                'required',
                'regex:/^\\d{4}-\\d{2}$/',
                Rule::unique('grupo_contrato_descontos', 'competencia')
                    ->where('grupo_contrato_id', $this->route('grupoContrato')?->id),
            ],
            'valor' => ['required', 'numeric', 'min:0', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'valor_teto' => ['nullable', 'numeric', 'min:0', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'competencia' => (string) $this->input('competencia'),
            'valor' => $this->input('valor') === null || $this->input('valor') === '' ? '0.00' : (string) $this->input('valor'),
            'valor_teto' => $this->input('valor_teto') === '' ? null : $this->input('valor_teto'),
            'observacao' => trim((string) $this->input('observacao', '')) ?: null,
        ]);
    }
}
