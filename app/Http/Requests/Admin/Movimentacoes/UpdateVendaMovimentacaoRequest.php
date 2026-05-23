<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Validation\Rule;

class UpdateVendaMovimentacaoRequest extends StoreVendaMovimentacaoRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->camposCalculadosProibidos(), [
            'numero_nf' => ['required', 'string', 'max:255'],
            'id_empresa_origem' => ['required', 'integer', Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class)],
            'id_empresa_destino' => ['required', 'integer', Rule::exists('empresas', 'id')->where('entidade_type', Cliente::class)],
            'id_unidade_negocio_estoque' => ['nullable', 'integer', Rule::exists('unidades_negocio', 'id')],
            'id_unidade_negocio_faturamento' => ['prohibited'],
            'id_fruta' => ['required', 'integer', Rule::exists('frutas', 'id')->where(fn ($q) => $q->where('kg_por_unidade_medicao', '>', 0))],
            'qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'valor_nf_total' => ['required', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:5000'],
            'id_frete' => ['nullable', 'integer', Rule::exists('fretes', 'id')->where('status_situacao', FreteStatusSituacao::ABERTA->value)],
            'motivo_substituicao' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $merge = ['numero_nf' => trim((string) $this->input('numero_nf'))];
        if ($this->has('qtd_fruta_um')) {
            $qtdRaw = $this->input('qtd_fruta_um');
            $merge['qtd_fruta_um'] = is_string($qtdRaw) && str_contains($qtdRaw, ',')
                ? TextoCadastro::normalizarDecimalNaoNegativo($qtdRaw)
                : number_format(max(0, (float) $qtdRaw), 2, '.', '');
        }
        if ($this->has('valor_nf_total')) {
            $valorRaw = $this->input('valor_nf_total');
            $merge['valor_nf_total'] = is_string($valorRaw) && str_contains($valorRaw, ',')
                ? TextoCadastro::normalizarValorMonetarioBrasileiro($valorRaw)
                : number_format(max(0, (float) $valorRaw), 2, '.', '');
        }
        $empresaOrigem = Empresa::query()->with('entidade')->find((int) $this->input('id_empresa_origem'));
        $origem = $empresaOrigem?->entidade instanceof UnidadeNegocio ? $empresaOrigem->entidade : null;

        if ($origem !== null && $origem->is_unidade_producao) {
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
}
