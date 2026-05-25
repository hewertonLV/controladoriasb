<?php

namespace App\Support\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;

final class CaptacaoLoteTimelineUi
{
    /**
     * @return list<array{
     *     status: CaptacaoLoteStatus,
     *     label: string,
     *     descricao: string,
     *     estado: 'concluido'|'atual'|'pendente',
     * }>
     */
    public static function passos(CaptacaoLote $lote): array
    {
        $sequencia = $lote->tipo === CaptacaoLoteTipo::RomaneioManual
            ? self::sequenciaRomaneioManual()
            : self::sequenciaCaptacaoPedidos();

        $indiceAtual = self::indiceStatus($sequencia, $lote->status);

        $passos = [];
        foreach ($sequencia as $indice => $status) {
            $passos[] = [
                'status' => $status,
                'label' => $status->label(),
                'descricao' => self::descricao($status, $lote->tipo),
                'estado' => self::estadoPasso($indice, $indiceAtual),
            ];
        }

        return $passos;
    }

    public static function descricaoAtual(CaptacaoLote $lote): string
    {
        return self::descricao($lote->status, $lote->tipo);
    }

    /**
     * @return list<CaptacaoLoteStatus>
     */
    private static function sequenciaCaptacaoPedidos(): array
    {
        return [
            CaptacaoLoteStatus::CaptacaoEmAndamento,
            CaptacaoLoteStatus::AguardandoTransferenciaCigan,
            CaptacaoLoteStatus::TransferenciaCiganIniciada,
            CaptacaoLoteStatus::AguardandoVinculoFrete,
            CaptacaoLoteStatus::TransferenciaFinalizada,
            CaptacaoLoteStatus::FaturamentoCiganIniciado,
            CaptacaoLoteStatus::VendasFinalizadas,
        ];
    }

    /**
     * @return list<CaptacaoLoteStatus>
     */
    private static function sequenciaRomaneioManual(): array
    {
        return [
            CaptacaoLoteStatus::CaptacaoEmAndamento,
            CaptacaoLoteStatus::AguardandoTransferenciaCigan,
            CaptacaoLoteStatus::TransferenciaCiganIniciada,
            CaptacaoLoteStatus::TransferenciaFinalizada,
        ];
    }

    /**
     * @param  list<CaptacaoLoteStatus>  $sequencia
     */
    private static function indiceStatus(array $sequencia, CaptacaoLoteStatus $status): int
    {
        foreach ($sequencia as $indice => $item) {
            if ($item === $status) {
                return $indice;
            }
        }

        return max(0, count($sequencia) - 1);
    }

    private static function estadoPasso(int $indice, int $indiceAtual): string
    {
        if ($indice < $indiceAtual) {
            return 'concluido';
        }

        if ($indice === $indiceAtual) {
            return 'atual';
        }

        return 'pendente';
    }

    private static function descricao(CaptacaoLoteStatus $status, CaptacaoLoteTipo $tipo): string
    {
        if ($tipo === CaptacaoLoteTipo::RomaneioManual) {
            return match ($status) {
                CaptacaoLoteStatus::CaptacaoEmAndamento => 'Monte as frutas e as quantidades em caixas. Quando terminar, feche o romaneio para liberar a transferência.',
                CaptacaoLoteStatus::AguardandoTransferenciaCigan => 'Romaneio fechado. Inicie a transferência no SB e gere o arquivo Cigan para o HUB efetuar no sistema fiscal.',
                CaptacaoLoteStatus::TransferenciaCiganIniciada => 'Arquivo Cigan disponível. Aguardando o HUB efetuar as transferências no Cigan. Depois, conclua a transferência no SB.',
                CaptacaoLoteStatus::TransferenciaFinalizada => 'Abastecimento e transferências concluídos. Este lote manual não possui etapa de vendas.',
                default => $status->label(),
            };
        }

        return match ($status) {
            CaptacaoLoteStatus::CaptacaoEmAndamento => 'Pedidos, rotas e quantidades podem ser editados na matriz ou no app. Finalize a captação do faturamento quando o dia estiver completo.',
            CaptacaoLoteStatus::AguardandoTransferenciaCigan => 'Captação do faturamento finalizada. Inicie a transferência no SB (arquivo Cigan); em seguida o HUB efetua no Cigan.',
            CaptacaoLoteStatus::TransferenciaCiganIniciada => 'Arquivo Cigan gerado. Aguardando o HUB efetuar as transferências no Cigan. As quantidades do romaneio estão travadas no SB.',
            CaptacaoLoteStatus::AguardandoVinculoFrete => 'Transferências gerenciais validadas no SB. Vincule fretes se necessário (opcional) e conclua a etapa de frete.',
            CaptacaoLoteStatus::TransferenciaFinalizada => 'Transferência encerrada. Romaneio principal imutável. Ajuste preços na matriz até Jefferson iniciar o faturamento no Cigan.',
            CaptacaoLoteStatus::FaturamentoCiganIniciado => 'Aguardando Jefferson finalizar as vendas no Cigan antes de efetivar no SB.',
            CaptacaoLoteStatus::VendasFinalizadas => 'Vendas efetivadas no SB. Ciclo do lote concluído.',
            default => $status->label(),
        };
    }
}
