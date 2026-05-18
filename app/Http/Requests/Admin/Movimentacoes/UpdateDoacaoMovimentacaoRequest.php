<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Models\Cliente;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoacaoMovimentacaoRequest extends FormRequest
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
            'id_fruta' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'valor_nf_total' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
            'valor_total_movimentacao' => ['prohibited'],
            'valor_frete_rateio' => ['prohibited'],
            'valor_frete_um' => ['prohibited'],
            'valor_frete_kg' => ['prohibited'],
            'id_frete' => ['prohibited'],
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
            'status_movimentacao_id' => ['prohibited'],
            'status_transferencia' => ['prohibited'],
            'transferencia_origem_id' => ['prohibited'],
            'pareada_movimentacao_id' => ['prohibited'],
            'numero_nf_destino' => ['prohibited'],
            'qtd_recebida_um' => ['prohibited'],
            'qtd_recebida_kg' => ['prohibited'],
            'status_recebimento' => ['prohibited'],
            'observacao_recebimento' => ['prohibited'],
        ];

        return array_merge($proibidos, [
            'qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'id_empresa_destino' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('empresas', 'id')->where('entidade_type', Cliente::class),
            ],
            'motivo_doacao' => ['required', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:4000'],
            'numero_nf_origem' => ['nullable', 'string', 'max:120'],
            'motivo_substituicao' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qtd_fruta_um' => 'quantidade na unidade de medida',
            'id_empresa_destino' => 'cliente de destino',
            'motivo_doacao' => 'motivo da doação',
            'observacao' => 'observação',
            'numero_nf_origem' => 'número da NF na origem',
            'motivo_substituicao' => 'motivo da substituição',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('qtd_fruta_um')) {
            $this->merge([
                'qtd_fruta_um' => TextoCadastro::normalizarDecimalNaoNegativo(
                    $this->input('qtd_fruta_um'),
                ),
            ]);
        }

        if ($this->has('id_empresa_destino') && ($this->input('id_empresa_destino') === '' || $this->input('id_empresa_destino') === '0')) {
            $this->merge(['id_empresa_destino' => null]);
        }
    }
}
