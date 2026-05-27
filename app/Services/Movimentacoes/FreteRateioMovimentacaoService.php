<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Empresa;
use App\Models\Frete;
use App\Models\Fruta;
use App\Support\Movimentacoes\VendaCustoOperacionalHub;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FreteRateioMovimentacaoService
{
    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $replayLinhaTempoEstoque,
    ) {}

    public function recalcular(int $idFrete): void
    {
        DB::transaction(function () use ($idFrete): void {
            $frete = Frete::query()->whereKey($idFrete)->lockForUpdate()->first();
            if ($frete === null) {
                return;
            }

            /** @var Collection<int, Movimentacao> $movimentacoes */
            $movimentacoes = Movimentacao::query()
                ->vigentesParaCalculo()
                ->where('id_frete', $idFrete)
                ->ordenarLinhaDoTempo()
                ->lockForUpdate()
                ->get();

            $baseRateio = $movimentacoes
                ->filter(fn (Movimentacao $movimentacao): bool => $this->participaDoRateio($movimentacao))
                ->values();

            if ($baseRateio->isEmpty()) {
                $frete->forceFill(['valor_fruta_kg' => '0.00'])->save();

                return;
            }

            $totalKg = round((float) $baseRateio->sum(static fn (Movimentacao $m): float => (float) $m->qtd_fruta_kg), 2);
            if ($totalKg <= 0) {
                return;
            }

            $valorFreteKg = round((float) $frete->valor / $totalKg, 2);
            $rateiosPorMovimentacao = $this->calcularRateiosEmCentavos($baseRateio, (float) $frete->valor, $totalKg);
            $afetadosEntrada = collect();
            $transferencias = collect();
            $vendas = collect();

            foreach ($baseRateio as $movimentacao) {
                $qtdUm = (float) $movimentacao->qtd_fruta_um;
                $rateio = $rateiosPorMovimentacao[(int) $movimentacao->id] ?? 0.0;
                $freteUm = $qtdUm > 0 ? round($rateio / $qtdUm, 2) : 0.0;

                if ((int) $movimentacao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::Compra->value) {
                    $this->atualizarCompra($movimentacao, $valorFreteKg, $rateio, $freteUm);
                    $afetadosEntrada->push([
                        'unidade_id' => $this->unidadeDaEmpresa((int) $movimentacao->id_empresa_destino),
                        'fruta_id' => (int) $movimentacao->id_fruta,
                    ]);

                    continue;
                }

                if ((int) $movimentacao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::Transferencia->value) {
                    $this->atualizarFreteBasico($movimentacao, $valorFreteKg, $rateio, $freteUm);
                    $transferencias->push($movimentacao->id);

                    continue;
                }

                if ((int) $movimentacao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::Venda->value) {
                    $this->atualizarFreteBasico($movimentacao, $valorFreteKg, $rateio, $freteUm);
                    $vendas->push($movimentacao->id);

                    continue;
                }

                $this->atualizarFreteBasico($movimentacao, $valorFreteKg, $rateio, $freteUm);
            }

            $frete->forceFill(['valor_fruta_kg' => number_format($valorFreteKg, 2, '.', '')])->save();

            $this->reprocessarAfetados($afetadosEntrada);

            $afetadosTransferencia = collect();
            foreach ($transferencias as $transferenciaId) {
                $saida = Movimentacao::query()->whereKey($transferenciaId)->first();
                if ($saida === null) {
                    continue;
                }

                $qtdKg = (float) $saida->qtd_fruta_kg;
                $qtdUm = (float) $saida->qtd_fruta_um;
                $rateio = $rateiosPorMovimentacao[(int) $saida->id] ?? round($valorFreteKg * $qtdKg, 2);
                $freteUm = $qtdUm > 0 ? round($rateio / $qtdUm, 2) : 0.0;

                foreach ($this->atualizarEntradaTransferencia($saida, $valorFreteKg, $rateio, $freteUm) as $afetado) {
                    $afetadosTransferencia->push($afetado);
                }
            }

            $this->reprocessarAfetados($afetadosTransferencia);

            foreach ($vendas as $vendaId) {
                $venda = Movimentacao::query()->whereKey($vendaId)->first();
                if ($venda === null) {
                    continue;
                }

                $qtdKg = (float) $venda->qtd_fruta_kg;
                $qtdUm = (float) $venda->qtd_fruta_um;
                $rateio = $rateiosPorMovimentacao[(int) $venda->id] ?? round($valorFreteKg * $qtdKg, 2);
                $freteUm = $qtdUm > 0 ? round($rateio / $qtdUm, 2) : 0.0;

                $this->atualizarVenda($venda, $valorFreteKg, $rateio, $freteUm);
            }
        });
    }

    /**
     * Distribui o valor total do frete em centavos para garantir fechamento contábil.
     *
     * @param  Collection<int, Movimentacao>  $baseRateio
     * @return array<int, float>
     */
    private function calcularRateiosEmCentavos(Collection $baseRateio, float $valorFreteTotal, float $totalKg): array
    {
        $totalCentavos = (int) round($valorFreteTotal * 100);
        $linhas = $baseRateio
            ->map(function (Movimentacao $movimentacao) use ($totalCentavos, $totalKg): array {
                $centavosExatos = $totalKg > 0
                    ? ($totalCentavos * (float) $movimentacao->qtd_fruta_kg) / $totalKg
                    : 0.0;
                $centavosBase = (int) floor($centavosExatos);

                return [
                    'id' => (int) $movimentacao->id,
                    'centavos' => $centavosBase,
                    'fracao' => $centavosExatos - $centavosBase,
                ];
            })
            ->values();

        $centavosDistribuidos = (int) $linhas->sum('centavos');
        $centavosRestantes = $totalCentavos - $centavosDistribuidos;

        $ordemDistribuicao = $linhas
            ->sortBy([
                ['fracao', 'desc'],
                ['id', 'asc'],
            ])
            ->values()
            ->pluck('id')
            ->all();

        $rateios = $linhas
            ->mapWithKeys(static fn (array $linha): array => [$linha['id'] => (int) $linha['centavos']])
            ->all();

        for ($i = 0; $i < $centavosRestantes; $i++) {
            $id = $ordemDistribuicao[$i % max(1, count($ordemDistribuicao))] ?? null;
            if ($id !== null) {
                $rateios[$id]++;
            }
        }

        return array_map(static fn (int $centavos): float => round($centavos / 100, 2), $rateios);
    }

    private function reprocessarAfetados(Collection $afetados): void
    {
        $afetados
            ->unique(fn (array $afetado): string => $afetado['unidade_id'].'-'.$afetado['fruta_id'])
            ->each(function (array $afetado): void {
                $this->replayLinhaTempoEstoque->reprocessarUnidadeFruta(
                    (int) $afetado['unidade_id'],
                    (int) $afetado['fruta_id'],
                );
            });
    }

    private function participaDoRateio(Movimentacao $movimentacao): bool
    {
        if ((float) $movimentacao->qtd_fruta_kg <= 0) {
            return false;
        }

        return ! (
            (int) $movimentacao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::Transferencia->value
            && (int) $movimentacao->status_movimentacao_id === StatusMovimentacao::ID_ENTRADA
        );
    }

    private function atualizarCompra(Movimentacao $movimentacao, float $valorFreteKg, float $rateio, float $freteUm): void
    {
        $fruta = Fruta::query()->find((int) $movimentacao->id_fruta);
        if ($fruta === null) {
            return;
        }

        $qtdKg = (float) $movimentacao->qtd_fruta_kg;
        $qtdUm = (float) $movimentacao->qtd_fruta_um;
        if ($qtdKg <= 0 || $qtdUm <= 0) {
            return;
        }

        $valorNfTotal = (float) $movimentacao->valor_nf_total;
        $valorNfKg = round($valorNfTotal / $qtdKg, 2);
        $valorNfUm = round($valorNfTotal / $qtdUm, 2);
        $precoMedioKg = round($valorNfKg + (float) $movimentacao->valor_custo_operacional + $valorFreteKg + (float) $movimentacao->icms_convertido_kg, 2);
        $precoMedioUm = round($precoMedioKg * (float) $fruta->kg_por_unidade_medicao, 2);

        $movimentacao->forceFill([
            'valor_nf_kg' => number_format($valorNfKg, 2, '.', ''),
            'valor_nf_um' => number_format($valorNfUm, 2, '.', ''),
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
            'valor_frete_um' => number_format($freteUm, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoMedioUm, 2, '.', ''),
        ])->saveQuietly();
    }

    /**
     * @return array<int, array{unidade_id: int, fruta_id: int}>
     */
    private function atualizarEntradaTransferencia(Movimentacao $saida, float $valorFreteKg, float $rateio, float $freteUm): array
    {
        $entrada = $this->entradaPareadaAtiva($saida);
        if ($entrada === null) {
            return [];
        }

        $fruta = Fruta::query()->find((int) $entrada->id_fruta);
        $kgPorUm = $fruta !== null ? (float) $fruta->kg_por_unidade_medicao : 0.0;
        $precoEntradaKg = round((float) $saida->preco_medio_fruta_kg + $valorFreteKg + (float) $entrada->valor_custo_operacional + (float) $entrada->icms_convertido_kg, 2);
        $precoEntradaUm = round($precoEntradaKg * $kgPorUm, 2);
        $valorEntradaTotal = round($precoEntradaKg * (float) $entrada->qtd_fruta_kg, 2);
        $qtdUm = (float) $entrada->qtd_fruta_um;

        $entrada->forceFill([
            'valor_nf_total' => number_format($valorEntradaTotal, 2, '.', ''),
            'valor_nf_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'valor_nf_um' => number_format($qtdUm > 0 ? $valorEntradaTotal / $qtdUm : 0, 2, '.', ''),
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
            'valor_frete_um' => number_format($freteUm, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoEntradaUm, 2, '.', ''),
            'valor_total_movimentacao' => number_format($valorEntradaTotal, 2, '.', ''),
        ])->saveQuietly();

        if ($entrada->status_transferencia !== StatusTransferenciaOperacional::RECEBIDA_CONFORME->value) {
            return [];
        }

        return [[
            'unidade_id' => $this->unidadeDaEmpresa((int) $entrada->id_empresa_destino),
            'fruta_id' => (int) $entrada->id_fruta,
        ]];
    }

    private function atualizarVenda(Movimentacao $movimentacao, float $valorFreteKg, float $rateio, float $freteUm): void
    {
        $movimentacao->forceFill([
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
            'valor_frete_um' => number_format($freteUm, 2, '.', ''),
            'resultado_movimentacao' => number_format(round(
                (float) $movimentacao->valor_nf_total
                - (float) $movimentacao->valor_custo_saida
                - VendaCustoOperacionalHub::valorCoTotalDescontadoNaMargem($movimentacao)
                - $rateio,
                2
            ), 2, '.', ''),
        ])->saveQuietly();
    }

    private function atualizarFreteBasico(Movimentacao $movimentacao, float $valorFreteKg, float $rateio, float $freteUm): void
    {
        $movimentacao->forceFill([
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
            'valor_frete_um' => number_format($freteUm, 2, '.', ''),
        ])->saveQuietly();
    }

    private function unidadeDaEmpresa(int $empresaId): int
    {
        $empresa = Empresa::query()->findOrFail($empresaId);

        if (! $empresa->entidade instanceof UnidadeNegocio) {
            throw new \InvalidArgumentException('Empresa não resolve para unidade de negócio.');
        }

        return (int) $empresa->entidade->id;
    }

    private function entradaPareadaAtiva(Movimentacao $saida): ?Movimentacao
    {
        $id = (int) ($saida->pareada_movimentacao_id ?? 0);
        if ($id < 1) {
            return null;
        }

        return Movimentacao::query()
            ->whereKey($id)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->first();
    }
}
