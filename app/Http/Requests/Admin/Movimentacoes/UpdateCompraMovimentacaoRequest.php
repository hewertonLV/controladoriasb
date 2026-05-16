<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompraMovimentacaoRequest extends FormRequest
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
        $proibidos = [
            'id_movimentacao_estoque_old' => ['prohibited'],
            'id_movimentacao_estoque_new' => ['prohibited'],
            'id_empresa_origem' => ['prohibited'],
            'id_empresa_destino' => ['prohibited'],
            'id_fruta' => ['prohibited'],
            'qtd_fruta_um' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
            'id_frete' => ['prohibited'],
            'valor_frete_rateio' => ['prohibited'],
            'valor_frete_um' => ['prohibited'],
            'valor_frete_kg' => ['prohibited'],
            'id_custo_operacional' => ['prohibited'],
            'valor_custo_operacional' => ['prohibited'],
            'saldo_estoque_fruta_kg' => ['prohibited'],
            'saldo_estoque_fruta_um' => ['prohibited'],
            'preco_medio_fruta_kg' => ['prohibited'],
            'preco_medio_fruta_um' => ['prohibited'],
            'categoria_movimentacao_id' => ['prohibited'],
            'icms_convertido_kg' => ['prohibited'],
            'valor_icms_total' => ['prohibited'],
            'valor_icms_kg' => ['prohibited'],
            'valor_icms_um' => ['prohibited'],
            'data_movimentacao' => ['prohibited'],
            'versao' => ['prohibited'],
            'versao_replay' => ['prohibited'],
            'movimentacao_origem_id' => ['prohibited'],
            'status_registro' => ['prohibited'],
            'substituida_por_id' => ['prohibited'],
            'substituida_em' => ['prohibited'],
        ];

        return array_merge($proibidos, [
            'valor_nf_total' => ['required', 'numeric', 'min:0.01'],
            'motivo_substituicao' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'valor_nf_total' => 'valor total da NF',
            'motivo_substituicao' => 'motivo da substituição',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('valor_nf_total')) {
            $this->merge([
                'valor_nf_total' => TextoCadastro::normalizarValorMonetarioBrasileiro(
                    $this->input('valor_nf_total'),
                ),
            ]);
        }
    }
}
