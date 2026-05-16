<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoacaoMovimentacaoRequest extends FormRequest
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
            'versao' => ['prohibited'],
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

        return array_merge($proibidosSomenteBackend, [
            'id_empresa_origem' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class),
            ],
            'id_empresa_destino' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('empresas', 'id')->where('entidade_type', Cliente::class),
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
            'motivo_doacao' => ['required', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:4000'],
            'numero_nf_origem' => ['nullable', 'string', 'max:120'],
            'data_movimentacao' => ['nullable', 'date'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $idEmpOrigem = $this->input('id_empresa_origem');
            $idFruta = $this->input('id_fruta');
            if ($idEmpOrigem === null || $idEmpOrigem === '' || $idFruta === null || $idFruta === '') {
                return;
            }

            $empresa = Empresa::query()->find((int) $idEmpOrigem);
            $unidade = $empresa?->entidade;
            if (! $unidade instanceof UnidadeNegocio) {
                return;
            }

            if (! $unidade->possui_estoque) {
                $v->errors()->add('id_empresa_origem', 'A unidade de origem deve controlar estoque (possui_estoque).');

                return;
            }

            $existeEstoque = Estoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', (int) $idFruta)
                ->exists();

            if (! $existeEstoque) {
                $v->errors()->add(
                    'id_empresa_origem',
                    'A unidade selecionada não possui estoque registrado para a fruta informada.',
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
            'id_empresa_origem' => 'unidade de origem',
            'id_empresa_destino' => 'cliente de destino',
            'id_fruta' => 'fruta',
            'qtd_fruta_um' => 'quantidade na unidade de medida',
            'motivo_doacao' => 'motivo da doação',
            'observacao' => 'observação',
            'numero_nf_origem' => 'número da NF na origem',
            'data_movimentacao' => 'data da movimentação',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('qtd_fruta_um')) {
            $merge['qtd_fruta_um'] = TextoCadastro::normalizarDecimalNaoNegativo(
                $this->input('qtd_fruta_um'),
            );
        }

        $this->merge($merge);
    }
}
