<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoDemandaStatus;
use App\Enums\VendaNotaStatusConclusao;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Captacao\Pedido;
use App\Models\VendaNota;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CaptacaoDemandaRotaService
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    public function assertPodeReabrirRota(CaptacaoLote $lote, CaptacaoRota $rota): void
    {
        foreach ($this->demandasDaRota($lote, $rota) as $demanda) {
            $status = CaptacaoDemandaStatus::tryFrom((string) $demanda->status_demanda)
                ?? CaptacaoDemandaStatus::Aberto;

            if ($status !== CaptacaoDemandaStatus::Aberto) {
                $tipo = $demanda->tipo === CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA
                    ? 'transferência'
                    : 'venda';

                throw ValidationException::withMessages([
                    'rota' => "Não é possível reabrir a rota: demanda de {$tipo} está «{$status->label()}». "
                        .'Só é permitido reabrir com todas as demandas em Aberto.',
                ]);
            }
        }
    }

    public function removerDemandasAoReabrir(CaptacaoLote $lote, CaptacaoRota $rota): void
    {
        $demandas = $this->demandasDaRota($lote, $rota);

        foreach ($demandas->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) as $vinculo) {
            if ($vinculo->transferencia_origem_id !== null) {
                $this->transferencias->cancelarTransferenciaPendenteRecebimento(
                    (int) $vinculo->transferencia_origem_id,
                    'Reabertura de rota na captação',
                );
            }
        }

        foreach ($demandas->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) as $vinculo) {
            $this->removerNotasPendentesDemandaVenda($lote, $vinculo);
        }

        foreach ($demandas as $demanda) {
            $demanda->delete();
        }
    }

    public function marcarTransferenciaIniciada(int $transferenciaOrigemId): void
    {
        CaptacaoLoteMovimentacao::query()
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_demanda', CaptacaoDemandaStatus::Aberto->value)
            ->update(['status_demanda' => CaptacaoDemandaStatus::Iniciado->value]);
    }

    public function marcarTransferenciaConcluida(int $transferenciaOrigemId): void
    {
        CaptacaoLoteMovimentacao::query()
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_demanda', CaptacaoDemandaStatus::Iniciado->value)
            ->update(['status_demanda' => CaptacaoDemandaStatus::Concluido->value]);
    }

    public function marcarVendaIniciada(CaptacaoLoteMovimentacao $vinculo): void
    {
        if ($vinculo->status_demanda === CaptacaoDemandaStatus::Aberto->value) {
            $vinculo->update(['status_demanda' => CaptacaoDemandaStatus::Iniciado->value]);
        }
    }

    public function marcarVendaConcluidaPorNota(int $vendaNotaId): void
    {
        CaptacaoLoteMovimentacao::query()
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->where('venda_nota_id', $vendaNotaId)
            ->update(['status_demanda' => CaptacaoDemandaStatus::Concluido->value]);
    }

    /**
     * @return Collection<int, CaptacaoLoteMovimentacao>
     */
    private function demandasDaRota(CaptacaoLote $lote, CaptacaoRota $rota): Collection
    {
        return CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rota->id)
            ->get();
    }

    private function removerNotasPendentesDemandaVenda(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo): void
    {
        if ($vinculo->venda_nota_id !== null) {
            VendaNota::query()->whereKey($vinculo->venda_nota_id)->delete();

            return;
        }

        $vinculo->loadMissing('linhas');
        $idsPedido = $vinculo->linhas->pluck('id_pedido')->filter()->unique();

        foreach ($idsPedido as $idPedido) {
            $pedido = Pedido::query()->find($idPedido);
            if ($pedido === null) {
                continue;
            }

            $numeroNf = sprintf(
                'CAP-%s-%d-%d',
                $lote->data_referencia->format('Ymd'),
                $lote->id,
                $pedido->id_cliente,
            );

            VendaNota::query()
                ->where('numero_nf', $numeroNf)
                ->where('status_conclusao', VendaNotaStatusConclusao::Pendente->value)
                ->delete();
        }
    }
}
