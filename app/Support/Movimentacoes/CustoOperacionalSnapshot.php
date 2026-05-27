<?php

namespace App\Support\Movimentacoes;

use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Carbon;

/**
 * CO (R$/kg) na data da operação — gravado na movimentação e reutilizado em derivados/replay.
 */
final class CustoOperacionalSnapshot
{
    /**
     * @return array{id: int|null, valor: float}
     */
    public static function daMovimentacao(Movimentacao $movimentacao): array
    {
        return [
            'id' => $movimentacao->id_custo_operacional !== null ? (int) $movimentacao->id_custo_operacional : null,
            'valor' => round((float) $movimentacao->valor_custo_operacional, 2),
        ];
    }

    public static function movimentacaoPossuiSnapshot(Movimentacao $movimentacao): bool
    {
        return $movimentacao->id_custo_operacional !== null
            || round((float) $movimentacao->valor_custo_operacional, 2) > 0;
    }

    /**
     * CO vigente na unidade na data/hora informada (histórico com created_at).
     *
     * @return array{id: int|null, valor: float}
     */
    public static function vigenteNaData(int $idUnidadeNegocio, Carbon $dataReferencia): array
    {
        $co = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('created_at', '<=', $dataReferencia)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($co !== null) {
            return [
                'id' => (int) $co->id,
                'valor' => round((float) $co->custo_operacional, 2),
            ];
        }

        $unidade = UnidadeNegocio::query()->find($idUnidadeNegocio);

        return [
            'id' => null,
            'valor' => round((float) ($unidade?->custo_operacional ?? 0), 2),
        ];
    }

    /**
     * @return array{id: int|null, valor: float}
     */
    public static function vigenteNaDataUnidade(UnidadeNegocio $unidade, Carbon $dataReferencia): array
    {
        return self::vigenteNaData((int) $unidade->id, $dataReferencia);
    }

    /**
     * Nova movimentação: CO na data da operação (ou agora).
     *
     * @return array{id: int|null, valor: float, valor_formatado: string}
     */
    public static function paraNovaMovimentacao(UnidadeNegocio $unidade, ?Carbon $dataReferencia = null): array
    {
        $snapshot = self::vigenteNaDataUnidade($unidade, $dataReferencia ?? now());

        return [
            'id' => $snapshot['id'],
            'valor' => $snapshot['valor'],
            'valor_formatado' => number_format($snapshot['valor'], 2, '.', ''),
        ];
    }

    /**
     * @return array{id: int|null, valor: string}
     */
    public static function paraNovaMovimentacaoFormatado(UnidadeNegocio $unidade, ?Carbon $dataReferencia = null): array
    {
        $snapshot = self::paraNovaMovimentacao($unidade, $dataReferencia);

        return [
            'id' => $snapshot['id'],
            'valor' => $snapshot['valor_formatado'],
        ];
    }
}
