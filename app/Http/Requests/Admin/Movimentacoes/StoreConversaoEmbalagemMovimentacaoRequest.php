<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Models\UnidadeNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversaoEmbalagemMovimentacaoRequest extends FormRequest
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
            'id_empresa_origem' => [
                'required',
                'integer',
                Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class),
            ],
            'id_fruta_origem' => [
                'required',
                'integer',
                Rule::exists('frutas', 'id')->where(fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0)),
            ],
            'id_fruta_destino' => [
                'required',
                'integer',
                'different:id_fruta_origem',
                Rule::exists('frutas', 'id')->where(fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0)),
            ],
            'qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'qtd_resultante_um' => ['required', 'numeric', 'min:0.01'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qtd_fruta_um' => $this->normalizarDecimal($this->input('qtd_fruta_um')),
            'qtd_resultante_um' => $this->normalizarDecimal($this->input('qtd_resultante_um')),
        ]);
    }

    private function normalizarDecimal(mixed $value): mixed
    {
        if ($value === null || is_numeric($value)) {
            return $value;
        }

        return str_replace(',', '.', str_replace('.', '', (string) $value));
    }
}
