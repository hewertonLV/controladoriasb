<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoContratoMembroRequest extends FormRequest
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
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'competencia_inicio' => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
            'competencia_fim' => ['nullable', 'regex:/^\\d{4}-\\d{2}$/', 'gte:competencia_inicio'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente_id' => (int) $this->input('cliente_id'),
            'competencia_inicio' => (string) $this->input('competencia_inicio'),
            'competencia_fim' => $this->input('competencia_fim') === '' ? null : $this->input('competencia_fim'),
        ]);
    }
}
