<?php

namespace App\Http\Requests\Admin\Relatorios;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentabilidadeLojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::RELATORIOS_RENTABILIDADE_LOJA_VISUALIZAR) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'id_empresa_origem' => ['nullable', 'integer', 'exists:empresas,id'],
            'id_empresa_destino' => ['nullable', 'integer', 'exists:empresas,id'],
            'agrupamento' => ['nullable', Rule::in(['cliente', 'detalhe'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('data_inicio') && ! $this->has('data_fim')) {
            $this->merge([
                'data_inicio' => now()->startOfMonth()->toDateString(),
                'data_fim' => now()->toDateString(),
                'agrupamento' => 'cliente',
            ]);
        }
    }
}
