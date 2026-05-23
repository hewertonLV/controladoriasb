<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Http\Requests\Admin\Movimentacoes\Concerns\ValidaAcessoUnidadeNegocio;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVendaMovimentacaoRequest extends FormRequest
{
    use ValidaAcessoUnidadeNegocio;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->camposCalculadosProibidos(), [
            'numero_nf' => ['required', 'string', 'max:255'],
            'id_empresa_origem' => ['required', 'integer', Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class)],
            'id_unidade_negocio_centro_resultado' => ['nullable', 'integer', Rule::exists('unidades_negocio', 'id')],
            'id_unidade_negocio_estoque' => ['nullable', 'integer', Rule::exists('unidades_negocio', 'id')],
            'id_empresa_destino' => ['required', 'integer', Rule::exists('empresas', 'id')->where('entidade_type', Cliente::class)],
            'id_unidade_negocio_faturamento' => ['prohibited'],
            'aplicar_custo_operacional_hub' => ['nullable', 'boolean'],
            'id_unidade_negocio_hub_custo' => ['nullable', 'integer', Rule::exists('unidades_negocio', 'id')],
            'observacao' => ['nullable', 'string', 'max:5000'],
            'id_frete' => ['nullable', 'integer', Rule::exists('fretes', 'id')->where('status_situacao', FreteStatusSituacao::ABERTA->value)],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id_fruta' => ['required', 'integer', Rule::exists('frutas', 'id')->where(fn ($q) => $q->where('kg_por_unidade_medicao', '>', 0))],
            'itens.*.qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'itens.*.valor_nf_total' => ['required', 'numeric', 'min:0'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('itens', []) as $i => $item) {
                if (! isset($item['id_fruta'])) {
                    continue;
                }
                $fruta = Fruta::query()->find((int) $item['id_fruta']);
                if ($fruta !== null && (float) $fruta->kg_por_unidade_medicao <= 0) {
                    $v->errors()->add("itens.{$i}.id_fruta", 'A fruta precisa ter kg por unidade de medição maior que zero.');
                }
            }

            $empresaOrigem = Empresa::query()->with('entidade')->find((int) $this->input('id_empresa_origem'));
            $comercial = $empresaOrigem?->entidade instanceof UnidadeNegocio ? $empresaOrigem->entidade : null;

            if ($comercial !== null) {
                $this->validarAcessoUnidade($v, 'id_empresa_origem', (int) $comercial->id, 'Venda');

                if ($comercial->is_hub) {
                    $v->errors()->add('id_empresa_origem', 'Origem comercial não pode ser HUB. Selecione a loja e informe o HUB em saída física.');
                }

                if ($comercial->is_galpao_operacional) {
                    $v->errors()->add('id_empresa_origem', 'Galpão operacional não pode faturar NF. Selecione a unidade de faturamento.');
                }
            }

            $centro = $this->resolverUnidadeCentroResultadoEfetiva($comercial);
            if ($centro !== null) {
                $this->validarAcessoUnidade($v, 'id_unidade_negocio_centro_resultado', (int) $centro->id, 'Centro de resultado');

                if ($centro->is_hub) {
                    $v->errors()->add('id_unidade_negocio_centro_resultado', 'Centro de resultado não pode ser HUB.');
                }
            }

            $unidadePm = $this->resolverUnidadePmDebitoEfetiva($comercial, $centro);
            if ($unidadePm !== null) {
                $this->validarAcessoUnidade($v, 'id_unidade_negocio_estoque', (int) $unidadePm->id, 'Saída física');

                if (! $unidadePm->possui_estoque) {
                    $v->errors()->add('id_unidade_negocio_estoque', 'A unidade de saída física deve controlar estoque.');
                }

                foreach ((array) $this->input('itens', []) as $i => $item) {
                    if (blank($item['id_fruta'] ?? null)) {
                        continue;
                    }

                    $temEstoqueNaOrigem = Estoque::query()
                        ->where('id_unidade_negocio', $unidadePm->id)
                        ->where('id_fruta', (int) $item['id_fruta'])
                        ->where(function ($query): void {
                            $query->where('qtd_fruta_um', '>', 0)
                                ->orWhere('qtd_fruta_kg', '>', 0);
                        })
                        ->exists();

                    if (! $temEstoqueNaOrigem && ! $this->podeRealocarDaLoja($comercial, $centro, $unidadePm, (int) $item['id_fruta'])) {
                        $v->errors()->add(
                            "itens.{$i}.id_fruta",
                            'Esta fruta não possui estoque na saída física selecionada (nem saldo realocável da loja comercial).',
                        );
                    }
                }
            }

            if ($comercial !== null && $comercial->is_unidade_producao && $unidadePm !== null && ! $unidadePm->is_hub) {
                $aplicarHub = $this->boolean('aplicar_custo_operacional_hub');
                $hubInformado = ! blank($this->input('id_unidade_negocio_hub_custo'));

                if ($aplicarHub && ! $hubInformado) {
                    $v->errors()->add('id_unidade_negocio_hub_custo', 'Informe a unidade HUB para o custo operacional.');
                }

                if (! $aplicarHub && $hubInformado) {
                    $v->errors()->add('id_unidade_negocio_hub_custo', 'A unidade HUB de custo só deve ser informada com o switch ativado.');
                }

                if ($aplicarHub && $hubInformado) {
                    $hub = UnidadeNegocio::query()->find((int) $this->input('id_unidade_negocio_hub_custo'));
                    if ($hub !== null && ! $hub->is_hub) {
                        $v->errors()->add('id_unidade_negocio_hub_custo', 'A unidade de custo operacional deve ser HUB.');
                    }
                }
            } elseif (! blank($this->input('id_unidade_negocio_hub_custo')) || $this->boolean('aplicar_custo_operacional_hub')) {
                $v->errors()->add('aplicar_custo_operacional_hub', 'Custo operacional do HUB só se aplica quando a origem for unidade de produção e a saída física não for HUB.');
            }
        });
    }

    private function resolverUnidadeCentroResultadoEfetiva(?UnidadeNegocio $comercial): ?UnidadeNegocio
    {
        if ($comercial === null) {
            return null;
        }

        $idCentro = $this->input('id_unidade_negocio_centro_resultado');
        if (blank($idCentro)) {
            return $comercial;
        }

        return UnidadeNegocio::query()->find((int) $idCentro);
    }

    private function resolverUnidadePmDebitoEfetiva(?UnidadeNegocio $comercial, ?UnidadeNegocio $centro): ?UnidadeNegocio
    {
        if ($comercial === null || $centro === null) {
            return null;
        }

        $idEstoque = $this->input('id_unidade_negocio_estoque');
        if (blank($idEstoque)) {
            return $centro;
        }

        return UnidadeNegocio::query()->find((int) $idEstoque);
    }

    private function podeRealocarDaLoja(?UnidadeNegocio $comercial, ?UnidadeNegocio $centro, UnidadeNegocio $pmDebito, int $idFruta): bool
    {
        if ($comercial === null || $centro === null || ! $pmDebito->is_hub || $centro->id !== $comercial->id) {
            return false;
        }

        return Estoque::query()
            ->where('id_unidade_negocio', $comercial->id)
            ->where('id_fruta', $idFruta)
            ->where(function ($query): void {
                $query->where('qtd_fruta_um', '>', 0)
                    ->orWhere('qtd_fruta_kg', '>', 0);
            })
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        $itens = [];
        foreach ((array) $this->input('itens', []) as $key => $item) {
            if (
                blank($item['id_fruta'] ?? null)
                && blank($item['qtd_fruta_um'] ?? null)
                && blank($item['valor_nf_total'] ?? null)
            ) {
                continue;
            }

            $itens[$key] = $item;
            if (array_key_exists('qtd_fruta_um', $item)) {
                $qtdRaw = $item['qtd_fruta_um'];
                $itens[$key]['qtd_fruta_um'] = is_string($qtdRaw) && str_contains($qtdRaw, ',')
                    ? TextoCadastro::normalizarDecimalNaoNegativo($qtdRaw)
                    : number_format(max(0, (float) $qtdRaw), 2, '.', '');
            }
            if (array_key_exists('valor_nf_total', $item)) {
                $valorRaw = $item['valor_nf_total'];
                $itens[$key]['valor_nf_total'] = is_string($valorRaw) && str_contains($valorRaw, ',')
                    ? TextoCadastro::normalizarValorMonetarioBrasileiro($valorRaw)
                    : number_format(max(0, (float) $valorRaw), 2, '.', '');
            }
        }

        $empresaOrigem = Empresa::query()->with('entidade')->find((int) $this->input('id_empresa_origem'));
        $comercial = $empresaOrigem?->entidade instanceof UnidadeNegocio ? $empresaOrigem->entidade : null;
        $centro = $this->resolverUnidadeCentroResultadoEfetiva($comercial);
        $pmDebito = $this->resolverUnidadePmDebitoEfetiva($comercial, $centro);

        $merge = [
            'numero_nf' => trim((string) $this->input('numero_nf')),
            'itens' => $itens,
        ];

        if ($comercial !== null && $comercial->is_unidade_producao && $pmDebito !== null && ! $pmDebito->is_hub) {
            $merge['aplicar_custo_operacional_hub'] = $this->boolean('aplicar_custo_operacional_hub', true);
            if (! $merge['aplicar_custo_operacional_hub']) {
                $merge['id_unidade_negocio_hub_custo'] = null;
            }
        } else {
            $merge['aplicar_custo_operacional_hub'] = false;
            $merge['id_unidade_negocio_hub_custo'] = null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function camposCalculadosProibidos(): array
    {
        return [
            'categoria_movimentacao_id' => ['prohibited'],
            'status_movimentacao_id' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
            'valor_custo_saida' => ['prohibited'],
            'resultado_movimentacao' => ['prohibited'],
            'valor_total_movimentacao' => ['prohibited'],
            'data_emissao' => ['prohibited'],
            'saldo_estoque_fruta_kg' => ['prohibited'],
            'saldo_estoque_fruta_um' => ['prohibited'],
            'preco_medio_fruta_kg' => ['prohibited'],
            'preco_medio_fruta_um' => ['prohibited'],
            'valor_icms_total' => ['prohibited'],
            'valor_icms_kg' => ['prohibited'],
            'valor_icms_um' => ['prohibited'],
            'icms_convertido_kg' => ['prohibited'],
            'versao' => ['prohibited'],
            'versao_replay' => ['prohibited'],
            'status_registro' => ['prohibited'],
        ];
    }
}
