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
            CaptacaoLoteStatus::SaidaEstoqueFisico,
            CaptacaoLoteStatus::AguardandoVinculoFrete,
            CaptacaoLoteStatus::TransferenciaFinalizada,
            CaptacaoLoteStatus::FaturamentoCiganIniciado,
            CaptacaoLoteStatus::VincularRotasNosPedidos,
            CaptacaoLoteStatus::VincularFreteVenda,
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
                CaptacaoLoteStatus::CaptacaoEmAndamento => 'Monte as frutas e as quantidades em caixas. Quando terminar, confirme a solicitação para liberar a transferência.',
                CaptacaoLoteStatus::AguardandoTransferenciaCigan => 'Solicitação confirmada. Pronto para iniciar a transferência no SB e gerar o arquivo Cigam para o HUB efetuar no sistema fiscal.',
                CaptacaoLoteStatus::TransferenciaCiganIniciada => 'Arquivo Cigam disponível. Aguardando o HUB efetuar as transferências no Cigam. Depois, conclua a transferência no SB.',
                CaptacaoLoteStatus::TransferenciaFinalizada => 'Abastecimento e transferências concluídos. Este lote manual não possui etapa de vendas.',
                default => $status->label(),
            };
        }

        return match ($status) {
            CaptacaoLoteStatus::CaptacaoEmAndamento => 'Pedidos, rotas e quantidades podem ser editados na matriz ou no app. Finalize a captação do faturamento quando o dia estiver completo.',
            CaptacaoLoteStatus::AguardandoTransferenciaCigan => 'Captação do faturamento finalizada. Pronto para iniciar a transferência no SB (arquivo Cigam); em seguida o HUB efetua no Cigam.',
            CaptacaoLoteStatus::TransferenciaCiganIniciada => 'Arquivo Cigam gerado. Aguardando o HUB efetuar as transferências no Cigam e o envio da NF. As quantidades do romaneio estão travadas no SB.',
            CaptacaoLoteStatus::SaidaEstoqueFisico => 'NF recebida. Defina por loja se a saída física na venda será do galpão ou do HUB (aba Saída estoque físico) e conclua para efetivar as transferências no SB.',
            CaptacaoLoteStatus::AguardandoVinculoFrete => 'Transferências gerenciais efetivadas no SB. Vincule fretes na aba Frete HUB x CD (opcional) e conclua a etapa de frete.',
            CaptacaoLoteStatus::TransferenciaFinalizada => 'Transferência encerrada. Romaneio principal imutável. Ajuste preços na matriz até iniciar o faturamento no Cigam.',
            CaptacaoLoteStatus::FaturamentoCiganIniciado => 'Baixe o TXT de vendas na aba Arquivo Cigam Venda, importe no Cigam e envie a NF no SB para efetivar as movimentações de venda. Preços travados na matriz.',
            CaptacaoLoteStatus::VincularRotasNosPedidos => 'NF recebida e vendas movimentadas no SB. Vincule a rota de cada loja (aba Rotas) e a ordem de carregamento (aba Por rota). Quando tudo estiver preenchido, clique em Concluir rotas e carregamento.',
            CaptacaoLoteStatus::VincularFreteVenda => 'Rotas concluídas. Na aba Frete Vendas, vincule frete por loja se necessário (opcional) e clique em Concluir frete venda.',
            CaptacaoLoteStatus::VendasFinalizadas => 'Vendas e fretes concluídos no SB. Ciclo do lote encerrado.',
            default => $status->label(),
        };
    }
}
