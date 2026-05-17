<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Models\Empresa;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransferenciaMovimentacaoRequest extends FormRequest
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
            'valor_nf_total' => ['prohibited'],
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

        $empresaUnidade = [
            'required',
            'integer',
            'min:1',
            Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class),
        ];

        return array_merge($proibidosSomenteBackend, [
            'id_empresa_origem' => $empresaUnidade,
            'id_empresa_destino' => $empresaUnidade,
            'id_fruta' => [
                'required_without:itens',
                'integer',
                'min:1',
                Rule::exists('frutas', 'id')->where(
                    fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0),
                ),
            ],
            'qtd_fruta_um' => ['required_without:itens', 'numeric', 'min:0.01'],
            'itens' => ['sometimes', 'array', 'min:1'],
            'itens.*.id_fruta' => [
                'required_with:itens',
                'integer',
                'min:1',
                Rule::exists('frutas', 'id')->where(
                    fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0),
                ),
            ],
            'itens.*.qtd_fruta_um' => ['required_with:itens', 'numeric', 'min:0.01'],
            'numero_nf_origem' => ['nullable', 'string', 'max:120'],
            'id_frete' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('fretes', 'id')->where('status_situacao', FreteStatusSituacao::ABERTA->value),
            ],
            'observacao' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $idOrig = (int) $this->input('id_empresa_origem');
            $idDest = (int) $this->input('id_empresa_destino');
            if ($idOrig > 0 && $idDest > 0 && $idOrig === $idDest) {
                $v->errors()->add('id_empresa_destino', 'Origem e destino não podem ser a mesma unidade de negócio.');
            }

            foreach (['id_empresa_origem' => 'origem', 'id_empresa_destino' => 'destino'] as $campo => $rotulo) {
                $idEmp = $this->input($campo);
                if ($idEmp === null || $idEmp === '') {
                    continue;
                }

                $empresa = Empresa::query()->find((int) $idEmp);
                $entidade = $empresa?->entidade;
                if ($entidade instanceof UnidadeNegocio && ! $entidade->possui_estoque) {
                    $v->errors()->add(
                        $campo,
                        "A unidade de {$rotulo} deve controlar estoque (possui_estoque).",
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_empresa_origem' => 'unidade de origem',
            'id_empresa_destino' => 'unidade de destino',
            'id_fruta' => 'fruta',
            'qtd_fruta_um' => 'quantidade na unidade de medida',
            'itens.*.id_fruta' => 'fruta',
            'itens.*.qtd_fruta_um' => 'quantidade na unidade de medida',
            'numero_nf_origem' => 'número da NF na origem',
            'id_frete' => 'frete',
            'observacao' => 'observação',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('qtd_fruta_um')) {
            $merge['qtd_fruta_um'] = TextoCadastro::normalizarDecimalNaoNegativo($this->input('qtd_fruta_um'));
        }

        if ($this->has('itens')) {
            $itens = [];
            foreach ((array) $this->input('itens', []) as $key => $item) {
                if (blank($item['id_fruta'] ?? null) && blank($item['qtd_fruta_um'] ?? null)) {
                    continue;
                }

                $itens[$key] = $item;
                if (array_key_exists('qtd_fruta_um', $item)) {
                    $itens[$key]['qtd_fruta_um'] = TextoCadastro::normalizarDecimalNaoNegativo($item['qtd_fruta_um']);
                }
            }

            $merge['itens'] = $itens;
        }

        if (! $this->filled('id_frete')) {
            $merge['id_frete'] = null;
        }

        $this->merge($merge);
    }
}
