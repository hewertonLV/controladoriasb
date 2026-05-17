<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\StatusRecebimentoTransferencia;
use App\Models\Movimentacao;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmarRecebimentoTransferenciaRequest extends FormRequest
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
            'valor_nf_total' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
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
            'motivo_substituicao' => ['prohibited'],
            'substituida_em' => ['prohibited'],
            'status_movimentacao_id' => ['prohibited'],
            'status_transferencia' => ['prohibited'],
            'transferencia_origem_id' => ['prohibited'],
            'pareada_movimentacao_id' => ['prohibited'],
            'numero_nf_origem' => ['prohibited'],
            'observacao' => ['prohibited'],
        ];

        return array_merge($proibidos, [
            'numero_nf_destino' => ['nullable', 'string', 'max:120'],
            'qtd_recebida_um' => ['required', 'numeric', 'min:0.01'],
            'status_recebimento' => ['required', 'string', Rule::in(StatusRecebimentoTransferencia::values())],
            'observacao_recebimento' => [
                Rule::requiredIf(fn (): bool => mb_strtoupper(trim((string) $this->input('status_recebimento', '')), 'UTF-8') === StatusRecebimentoTransferencia::DIVERGENTE->value),
                'nullable',
                'string',
                'max:4000',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'numero_nf_destino' => 'número da NF no destino',
            'qtd_recebida_um' => 'quantidade recebida (UM)',
            'status_recebimento' => 'status do recebimento',
            'observacao_recebimento' => 'observação do recebimento',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('qtd_recebida_um')) {
            $merge['qtd_recebida_um'] = TextoCadastro::normalizarDecimalNaoNegativo($this->input('qtd_recebida_um'));
        }

        $this->merge($merge);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = mb_strtoupper(trim((string) $this->input('status_recebimento', '')), 'UTF-8');
            if ($status !== StatusRecebimentoTransferencia::CONFORME->value) {
                return;
            }

            $transferenciaOrigem = $this->route('transferenciaOrigem');
            if (! $transferenciaOrigem instanceof Movimentacao) {
                return;
            }

            $entrada = $transferenciaOrigem->movimentacaoPareada;
            if (! $entrada instanceof Movimentacao) {
                return;
            }

            $qtdRecebida = round((float) $this->input('qtd_recebida_um'), 2);
            $qtdEnviada = round((float) $entrada->qtd_fruta_um, 2);

            if (abs($qtdRecebida - $qtdEnviada) > 0.001) {
                $validator->errors()->add(
                    'qtd_recebida_um',
                    'Recebimento conforme exige quantidade recebida igual à enviada. Marque Divergente para informar outra quantidade.',
                );
            }
        });
    }
}
