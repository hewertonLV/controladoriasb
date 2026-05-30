<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Validation\ValidationException;

/**
 * Gera TXT simplificado para demanda de transferência da rota (várias frutas).
 */
final class CaptacaoDemandaTransferenciaCigamGerador
{
    public function gerar(CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): string
    {
        $lote->loadMissing(['unidadeFaturamento', 'unidadeHubOrigem']);
        $demanda->loadMissing(['linhas.fruta', 'fruta']);

        $origem = UnidadeNegocio::query()->find($demanda->id_unidade_negocio_origem);
        $destino = UnidadeNegocio::query()->find($lote->id_unidade_negocio_galpao);

        if ($origem === null || $destino === null) {
            throw ValidationException::withMessages([
                'demanda' => 'Dados insuficientes para gerar arquivo Cigam.',
            ]);
        }

        $linhas = $demanda->linhas;
        if ($linhas->isEmpty() && $demanda->id_fruta !== null) {
            $fruta = $demanda->fruta ?? Fruta::query()->find($demanda->id_fruta);
            if ($fruta === null) {
                throw ValidationException::withMessages([
                    'demanda' => 'Dados insuficientes para gerar arquivo Cigam.',
                ]);
            }

            $linhas = collect([(object) [
                'fruta' => $fruta,
                'qtd_um' => $demanda->qtd_um,
            ]]);
        }

        if ($linhas->isEmpty()) {
            throw ValidationException::withMessages([
                'demanda' => 'Demanda sem linhas de fruta para gerar Cigam.',
            ]);
        }

        $cabecalho = [
            'EDI NF CIGAM — DEMANDA TRANSFERÊNCIA ROTA',
            "Lote: {$lote->id}",
            "Demanda: {$demanda->id}",
            "Origem: {$origem->nome} (id {$origem->id})",
            "Destino: {$destino->nome} (id {$destino->id})",
            '',
            '--- Registros I (placeholder layout ADR-0105) ---',
        ];

        $registros = [];
        foreach ($linhas as $linha) {
            $fruta = $linha->fruta ?? Fruta::query()->find($linha->id_fruta ?? null);
            if ($fruta === null) {
                continue;
            }

            $qtd = rtrim(rtrim(number_format((float) $linha->qtd_um, 3, '.', ''), '0'), '.');
            $registros[] = sprintf(
                'I|%s|%s|%s',
                $fruta->id_cigam ?? $fruta->id,
                $qtd,
                $origem->id_cigam ?? $origem->id,
            );
            $cabecalho[] = "Fruta: {$fruta->nome} (id {$fruta->id}) · Quantidade UM: {$qtd}";
        }

        return implode("\r\n", array_merge($cabecalho, [''], $registros))."\r\n";
    }
}
