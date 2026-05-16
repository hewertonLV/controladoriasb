<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasTransferenciaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelarTransferenciaMovimentacaoAdminService
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferenciaMovimentacao,
        private readonly RecebimentoTransferenciaService $recebimentoTransferencia,
        private readonly ReconciliacaoTransferenciaService $reconciliacaoTransferencia,
        private readonly ReprocessaSaidasTransferenciaOrigem $reprocessaSaidasTransferenciaOrigem,
        private readonly ReplayEstoqueCompraService $replayEstoqueDestino,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(int $transferenciaOrigemId, User $user, string $motivo): void
    {
        DB::transaction(function () use ($transferenciaOrigemId, $user, $motivo): void {
            ['saida' => $saidaRef, 'entrada' => $entradaRef] = $this->transferenciaMovimentacao
                ->obterParAtivoPorTransferenciaOrigemId($transferenciaOrigemId);

            $saida = Movimentacao::query()->whereKey($saidaRef->id)->lockForUpdate()->firstOrFail();
            $entrada = Movimentacao::query()->whereKey($entradaRef->id)->lockForUpdate()->firstOrFail();

            if ((int) $saida->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Transferencia->value
                || (int) $entrada->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Transferencia->value) {
                throw new InvalidArgumentException('Somente transferências podem ser canceladas por este fluxo.');
            }

            if ($saida->status_registro !== MovimentacaoStatusRegistro::ATIVO->value
                || $entrada->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente versões ativas podem ser canceladas administrativamente.');
            }

            $frutaId = (int) $saida->id_fruta;

            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $saida->id_empresa_origem);
            $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $entrada->id_empresa_destino);
            $unidadeOrigem = $empresaOrigem->entidade;
            $unidadeDestino = $empresaDestino->entidade;
            if (! $unidadeOrigem instanceof UnidadeNegocio || ! $unidadeDestino instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Unidades de origem/destino inválidas.');
            }

            $estoqueOrigem = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $frutaId)
                ->lockForUpdate()
                ->firstOrFail();

            $estoqueDestino = Estoque::query()
                ->where('id_unidade_negocio', $unidadeDestino->id)
                ->where('id_fruta', $frutaId)
                ->lockForUpdate()
                ->firstOrFail();

            $idUnidadeOrigem = (int) $estoqueOrigem->id_unidade_negocio;
            $idUnidadeDestino = (int) $estoqueDestino->id_unidade_negocio;

            if ($saida->id_frete !== null) {
                Frete::query()->whereKey((int) $saida->id_frete)->lockForUpdate()->firstOrFail();
            }

            $dadosSaidaAntes = $this->auditoria->snapshotVersao($saida);
            $dadosEntradaAntes = $this->auditoria->snapshotVersao($entrada);
            $estoqueOrigemAntes = $this->auditoria->snapshotEstoque($estoqueOrigem);
            $estoqueDestinoAntes = $this->auditoria->snapshotEstoque($estoqueDestino);

            if ($entrada->status_transferencia === StatusTransferenciaOperacional::RECEBIDA_CONFORME->value) {
                $this->recebimentoTransferencia->reverterRecebimentoConformeParaCancelamentoAdministrativo($entrada);
                $entrada = Movimentacao::query()->whereKey($entrada->id)->lockForUpdate()->firstOrFail();
            }

            $this->transferenciaMovimentacao->estornarSaidaNoEstoqueOrigem($saida);

            $agora = now();

            $saida->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => $agora,
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            $entrada->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => $agora,
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            if ($saida->id_frete !== null) {
                $this->reconciliacaoTransferencia->recalcularRateioFreteParaTransferencias((int) $saida->id_frete);
            }

            $this->reprocessaSaidasTransferenciaOrigem->reprocessarSaidasTransferenciaNaUnidadeOrigem($idUnidadeOrigem, $frutaId);
            $this->replayEstoqueDestino->reprocessarEstoqueDestinoUnidadeFruta($idUnidadeDestino, $frutaId);

            $saida = $saida->fresh();
            $entrada = $entrada->fresh();
            if ($saida === null || $entrada === null) {
                throw new InvalidArgumentException('Movimentações não encontradas após cancelamento administrativo.');
            }

            $estoqueOrigemDepois = $this->auditoria->snapshotEstoque(
                Estoque::query()->whereKey($estoqueOrigem->id)->firstOrFail(),
            );
            $estoqueDestinoDepois = $this->auditoria->snapshotEstoque(
                Estoque::query()->whereKey($estoqueDestino->id)->firstOrFail(),
            );

            $dadosSaidaDepois = $this->auditoria->snapshotVersao($saida);
            $dadosEntradaDepois = $this->auditoria->snapshotVersao($entrada);

            $contextoPar = [
                'transferencia_origem_id' => $transferenciaOrigemId,
                'perna' => 'SAIDA',
                'pareada_movimentacao_id' => $entrada->id,
                'estoque_origem' => ['antes' => $estoqueOrigemAntes, 'depois' => $estoqueOrigemDepois],
                'estoque_destino' => ['antes' => $estoqueDestinoAntes, 'depois' => $estoqueDestinoDepois],
            ];

            $this->auditoria->registrarCancelamentoAdministrativo(
                $saida,
                $user,
                $motivo,
                $dadosSaidaAntes,
                $dadosSaidaDepois,
                $estoqueOrigemAntes,
                $estoqueOrigemDepois,
                $contextoPar,
            );

            $this->auditoria->registrarCancelamentoAdministrativo(
                $entrada,
                $user,
                $motivo,
                $dadosEntradaAntes,
                $dadosEntradaDepois,
                $estoqueDestinoAntes,
                $estoqueDestinoDepois,
                array_merge($contextoPar, ['perna' => 'ENTRADA', 'pareada_movimentacao_id' => $saida->id]),
            );
        });
    }
}
