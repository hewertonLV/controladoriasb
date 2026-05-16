<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusRecebimentoTransferencia;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Conferência física no destino (entrada pendente da transferência).
 */
final class RecebimentoTransferenciaService
{
    public function __construct(
        private readonly ReconciliacaoTransferenciaService $reconciliacao,
    ) {}

    /**
     * @param  array{
     *     numero_nf_destino?:string|null,
     *     qtd_recebida_um:numeric-string|float|int|string,
     *     status_recebimento:string,
     *     observacao_recebimento?:string|null,
     * }  $input
     */
    public function confirmarRecebimento(Movimentacao $entrada, array $input): void
    {
        DB::transaction(function () use ($entrada, $input): void {
            $entrada = Movimentacao::query()->whereKey($entrada->id)->lockForUpdate()->firstOrFail();
            $this->assertEntradaPendente($entrada);

            $saida = Movimentacao::query()
                ->whereKey((int) $entrada->pareada_movimentacao_id)
                ->lockForUpdate()
                ->firstOrFail();

            $statusRec = StatusRecebimentoTransferencia::tryFrom(mb_strtoupper(trim($input['status_recebimento']), 'UTF-8'));
            if ($statusRec === null) {
                throw new InvalidArgumentException('Status de recebimento inválido.');
            }

            $qtdRecUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_recebida_um']), 2);
            if ($qtdRecUm <= 0) {
                throw new InvalidArgumentException('Quantidade recebida deve ser maior que zero.');
            }

            $fruta = Fruta::query()->findOrFail((int) $entrada->id_fruta);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            $qtdRecKg = round($qtdRecUm * $kgPorUm, 2);

            $numeroNfDestino = isset($input['numero_nf_destino']) ? trim((string) $input['numero_nf_destino']) : null;
            if ($numeroNfDestino === '') {
                $numeroNfDestino = null;
            }

            $obsRec = isset($input['observacao_recebimento']) ? trim((string) $input['observacao_recebimento']) : null;
            if ($obsRec === '') {
                $obsRec = null;
            }

            if ($statusRec === StatusRecebimentoTransferencia::CONFORME) {
                $qtdEnvUm = (float) $entrada->qtd_fruta_um;
                if (abs($qtdRecUm - $qtdEnvUm) > 0.001) {
                    throw new InvalidArgumentException('Recebimento CONFORME exige quantidade recebida igual à enviada.');
                }

                if ($entrada->id_frete !== null) {
                    $this->reconciliacao->recalcularRateioFreteParaTransferencias((int) $entrada->id_frete);
                    $entrada->refresh();
                }

                $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $entrada->id_empresa_destino);
                $unidadeDestino = $this->unidadeDaEmpresa($empresaDestino);

                $estoqueDestino = Estoque::query()
                    ->where('id_unidade_negocio', $unidadeDestino->id)
                    ->where('id_fruta', $fruta->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->garantirPosicaoInicialSeNecessario($estoqueDestino, $unidadeDestino->id, $fruta->id);

                $posicaoDestino = MovimentacaoEstoque::query()
                    ->where('id_unidade_negocio', $unidadeDestino->id)
                    ->where('id_fruta', $fruta->id)
                    ->where('status_ultima_posicao', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $saldoUmAnt = (float) $posicaoDestino->qtd_fruta_um;
                $saldoKgAnt = (float) $posicaoDestino->qtd_fruta_kg;
                $Vprev = (float) $estoqueDestino->valor_total_acumulado;
                $Qprev = (float) $estoqueDestino->qtd_fruta_kg;

                $precoEntradaKg = (float) $entrada->preco_medio_fruta_kg;
                $Vlote = round($precoEntradaKg * $qtdRecKg, 2);
                $Vnovo = round($Vprev + $Vlote, 2);
                $saldoUmNovo = round($saldoUmAnt + $qtdRecUm, 2);
                $saldoKgNovo = round($saldoKgAnt + $qtdRecKg, 2);
                $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
                $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);
                $valorTotalSnapshot = round($saldoKgNovo * $precoConsolidadoKg, 2);

                $posicaoDestino->forceFill(['status_ultima_posicao' => false])->save();

                $novaMe = MovimentacaoEstoque::query()->create([
                    'id_estoque' => $estoqueDestino->id,
                    'id_unidade_negocio' => $unidadeDestino->id,
                    'id_fruta' => $fruta->id,
                    'movimentacao_id' => $entrada->id,
                    'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                    'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                    'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                    'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                    'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
                    'status_ultima_posicao' => true,
                ]);

                $entrada->forceFill([
                    'id_movimentacao_estoque_new' => $novaMe->id,
                    'numero_nf_destino' => $numeroNfDestino,
                    'qtd_recebida_um' => number_format($qtdRecUm, 2, '.', ''),
                    'qtd_recebida_kg' => number_format($qtdRecKg, 2, '.', ''),
                    'status_recebimento' => $statusRec->value,
                    'observacao_recebimento' => $obsRec,
                    'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                    'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                    'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_CONFORME->value,
                ])->saveQuietly();

                $saida->forceFill([
                    'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_CONFORME->value,
                ])->saveQuietly();

                $estoqueDestino->forceFill([
                    'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                    'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                    'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                    'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                    'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
                ])->save();

                return;
            }

            if ($obsRec === null) {
                throw new InvalidArgumentException('Observação do recebimento é obrigatória quando divergente.');
            }

            $entrada->forceFill([
                'numero_nf_destino' => $numeroNfDestino,
                'qtd_recebida_um' => number_format($qtdRecUm, 2, '.', ''),
                'qtd_recebida_kg' => number_format($qtdRecKg, 2, '.', ''),
                'status_recebimento' => $statusRec->value,
                'observacao_recebimento' => $obsRec,
                'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value,
            ])->saveQuietly();

            $saida->forceFill([
                'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value,
            ])->saveQuietly();
        });
    }

    /**
     * Remove do estoque do destino o efeito de um recebimento CONFORME já lançado (cancelamento administrativo).
     */
    public function reverterRecebimentoConformeParaCancelamentoAdministrativo(Movimentacao $entrada): void
    {
        if ((int) $entrada->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Transferencia->value) {
            throw new InvalidArgumentException('Movimentação não é transferência.');
        }
        if ((int) $entrada->status_movimentacao_id !== StatusMovimentacao::ID_ENTRADA) {
            throw new InvalidArgumentException('Somente perna de entrada pode ser revertida.');
        }
        if ($entrada->status_transferencia !== StatusTransferenciaOperacional::RECEBIDA_CONFORME->value) {
            throw new InvalidArgumentException('Somente entrada recebida conforme pode ser revertida neste fluxo.');
        }

        $fruta = Fruta::query()->findOrFail((int) $entrada->id_fruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;

        $qtdRecUm = (float) $entrada->qtd_recebida_um;
        $qtdRecKg = (float) $entrada->qtd_recebida_kg;
        if ($qtdRecUm <= 0 || $qtdRecKg <= 0) {
            throw new InvalidArgumentException('Quantidades recebidas inválidas para reversão.');
        }

        $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $entrada->id_empresa_destino);
        $unidadeDestino = $this->unidadeDaEmpresa($empresaDestino);

        $estoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', $fruta->id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->garantirPosicaoInicialSeNecessario($estoqueDestino, $unidadeDestino->id, $fruta->id);

        $posicaoDestino = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();

        $saldoUmAnt = (float) $posicaoDestino->qtd_fruta_um;
        $saldoKgAnt = (float) $posicaoDestino->qtd_fruta_kg;
        $Vprev = (float) $estoqueDestino->valor_total_acumulado;

        if ($saldoUmAnt + 1e-6 < $qtdRecUm || $saldoKgAnt + 1e-6 < $qtdRecKg) {
            throw new InvalidArgumentException('Saldo insuficiente no destino para reverter o recebimento.');
        }

        $precoEntradaKg = (float) $entrada->preco_medio_fruta_kg;
        $Vlote = round($precoEntradaKg * $qtdRecKg, 2);
        $saldoUmNovo = round($saldoUmAnt - $qtdRecUm, 2);
        $saldoKgNovo = round($saldoKgAnt - $qtdRecKg, 2);
        $Vnovo = round($Vprev - $Vlote, 2);
        if ($Vnovo < -0.01) {
            throw new InvalidArgumentException('Valor acumulado inconsistente ao reverter recebimento.');
        }

        $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
        $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);
        $valorTotalSnapshot = round($saldoKgNovo * $precoConsolidadoKg, 2);

        $posicaoDestino->forceFill(['status_ultima_posicao' => false])->save();

        $novaMe = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueDestino->id,
            'id_unidade_negocio' => $unidadeDestino->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoqueDestino->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
        ])->save();

        $entrada->forceFill([
            'id_movimentacao_estoque_new' => null,
            'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
        ])->saveQuietly();
    }

    private function assertEntradaPendente(Movimentacao $entrada): void
    {
        if ((int) $entrada->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Transferencia->value) {
            throw new InvalidArgumentException('Movimentação não é transferência.');
        }
        if ((int) $entrada->status_movimentacao_id !== StatusMovimentacao::ID_ENTRADA) {
            throw new InvalidArgumentException('Somente perna de entrada pode receber conferência.');
        }
        if ($entrada->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException('Somente versão ativa pode ser conferida.');
        }
        if ($entrada->status_transferencia !== StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value) {
            throw new InvalidArgumentException('Transferência não está pendente de recebimento.');
        }
    }

    private function unidadeDaEmpresa(Empresa $empresa): UnidadeNegocio
    {
        $entidade = $empresa->entidade;
        if (! $entidade instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Empresa destino inválida.');
        }

        return $entidade;
    }

    private function garantirPosicaoInicialSeNecessario(Estoque $estoque, int $idUnidade, int $idFruta): void
    {
        $existe = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->exists();

        if ($existe) {
            return;
        }

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);
    }
}
