<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Support\TextoCadastro;
use Illuminate\Validation\Rule;

trait ValidatesMovimentacaoAttributes
{
    /**
     * @return list<string>
     */
    protected function movimentacaoMonetaryFieldNames(): array
    {
        return [
            'valor_nf_total',
            'valor_nf_um',
            'valor_nf_kg',
            'valor_frete_rateio',
            'valor_frete_um',
            'valor_frete_kg',
            'valor_custo_operacional',
            'saldo_estoque_fruta_kg',
            'saldo_estoque_fruta_um',
            'preco_medio_fruta_kg',
            'preco_medio_fruta_um',
        ];
    }

    protected function prepareMovimentacaoForValidation(): void
    {
        $merge = [];

        foreach ($this->movimentacaoMonetaryFieldNames() as $campo) {
            if ($this->has($campo)) {
                $merge[$campo] = TextoCadastro::normalizarValorMonetarioBrasileiro($this->input($campo));
            }
        }

        if ($this->has('qtd_fruta_kg')) {
            $merge['qtd_fruta_kg'] = TextoCadastro::normalizarDecimalNaoNegativo($this->input('qtd_fruta_kg'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function movimentacaoBaseRules(CategoriaMovimentacaoTipo $categoriaFixa): array
    {
        return [
            'categoria_movimentacao_id' => [
                'required',
                'integer',
                Rule::in([$categoriaFixa->value]),
            ],
            'id_movimentacao_estoque_old' => ['nullable', 'integer', 'min:1', Rule::exists('movimentacoes_estoque', 'id')],
            'id_movimentacao_estoque_new' => ['nullable', 'integer', 'min:1', Rule::exists('movimentacoes_estoque', 'id')],
            'id_empresa_origem' => ['nullable', 'integer', 'min:1', Rule::exists('empresas', 'id')],
            'id_empresa_destino' => ['nullable', 'integer', 'min:1', Rule::exists('empresas', 'id')],
            'id_fruta' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('frutas', 'id')->where(
                    fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0),
                ),
            ],
            'valor_nf_total' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'valor_nf_um' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'valor_nf_kg' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'qtd_fruta_kg' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'id_frete' => ['nullable', 'integer', 'min:1', Rule::exists('fretes', 'id')],
            'valor_frete_rateio' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'valor_frete_um' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'valor_frete_kg' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'id_custo_operacional' => ['nullable', 'integer', 'min:1', Rule::exists('historico_c_o_un_ng', 'id')],
            'valor_custo_operacional' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'saldo_estoque_fruta_kg' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'saldo_estoque_fruta_um' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'preco_medio_fruta_kg' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'preco_medio_fruta_um' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function movimentacaoBaseAttributes(): array
    {
        return [
            'categoria_movimentacao_id' => 'categoria da movimentação',
            'id_movimentacao_estoque_old' => 'movimentação de estoque (anterior)',
            'id_movimentacao_estoque_new' => 'movimentação de estoque (nova)',
            'id_empresa_origem' => 'empresa origem',
            'id_empresa_destino' => 'empresa destino',
            'id_fruta' => 'fruta',
            'valor_nf_total' => 'valor NF total',
            'valor_nf_um' => 'valor NF por unidade de medida',
            'valor_nf_kg' => 'valor NF por kg',
            'qtd_fruta_kg' => 'quantidade da fruta (kg)',
            'id_frete' => 'frete',
            'valor_frete_rateio' => 'valor frete rateio',
            'valor_frete_um' => 'valor frete por unidade de medida',
            'valor_frete_kg' => 'valor frete por kg',
            'id_custo_operacional' => 'custo operacional (histórico)',
            'valor_custo_operacional' => 'valor custo operacional',
            'saldo_estoque_fruta_kg' => 'saldo estoque (kg)',
            'saldo_estoque_fruta_um' => 'saldo estoque (unidade)',
            'preco_medio_fruta_kg' => 'preço médio (kg)',
            'preco_medio_fruta_um' => 'preço médio (unidade)',
        ];
    }
}
