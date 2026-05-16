<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompraMovimentacaoRequest extends FormRequest
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
        $proibidosSomenteBackend = [
            'id_movimentacao_estoque_old' => ['prohibited'],
            'id_movimentacao_estoque_new' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
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
            'motivo_substituicao' => ['prohibited'],
            'substituida_em' => ['prohibited'],
        ];

        return array_merge($proibidosSomenteBackend, [
            'id_empresa_origem' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('empresas', 'id')->where('entidade_type', Fornecedor::class),
            ],
            'id_empresa_destino' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class),
            ],
            'id_fruta' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('frutas', 'id')->where(
                    fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0),
                ),
            ],
            'qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'valor_nf_total' => ['required', 'numeric', 'min:0.01'],
            'id_frete' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('fretes', 'id')->where('status_situacao', FreteStatusSituacao::ABERTA->value),
            ],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $idEmp = $this->input('id_empresa_destino');
            if ($idEmp === null || $idEmp === '') {
                return;
            }

            $empresa = Empresa::query()->find((int) $idEmp);
            $entidade = $empresa?->entidade;
            if ($entidade instanceof UnidadeNegocio && ! $entidade->possui_estoque) {
                $v->errors()->add(
                    'id_empresa_destino',
                    'A unidade de destino deve controlar estoque (possui_estoque).',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_empresa_origem' => 'empresa fornecedora',
            'id_empresa_destino' => 'unidade de destino',
            'id_fruta' => 'fruta',
            'qtd_fruta_um' => 'quantidade na unidade de medida',
            'valor_nf_total' => 'valor total da NF',
            'id_frete' => 'frete',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('valor_nf_total')) {
            $merge['valor_nf_total'] = TextoCadastro::normalizarValorMonetarioBrasileiro(
                $this->input('valor_nf_total'),
            );
        }

        if ($this->has('qtd_fruta_um')) {
            $merge['qtd_fruta_um'] = TextoCadastro::normalizarDecimalNaoNegativo(
                $this->input('qtd_fruta_um'),
            );
        }

        $this->merge($merge);
    }
}
