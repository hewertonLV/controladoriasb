<?php

namespace App\Services\Captacao;

use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\MovimentacaoEstoque;

final class CaptacaoDemandaEstoqueService
{
    /**
     * @return array{
     *     ok: bool,
     *     qtd_demanda: float,
     *     qtd_disponivel: float,
     *     qtd_falta: float,
     *     id_fruta: int,
     *     fruta_nome: string,
     * }
     */
    public function situacaoFruta(int $idUnidade, int $idFruta, float $qtdDemanda): array
    {
        $fruta = Fruta::query()->find($idFruta);
        $disponivel = $this->saldoUmDisponivel($idUnidade, $idFruta);
        $falta = max(0.0, round($qtdDemanda - $disponivel, 3));

        return [
            'ok' => $falta <= 0,
            'qtd_demanda' => round($qtdDemanda, 3),
            'qtd_disponivel' => $disponivel,
            'qtd_falta' => $falta,
            'id_fruta' => $idFruta,
            'fruta_nome' => (string) ($fruta?->nome ?? 'Fruta'),
        ];
    }

    /**
     * @param  list<array{id_fruta: int, qtd_um: float}>  $linhas
     * @return array{pode: bool, linhas: list<array<string, mixed>>}
     */
    public function validarLinhas(int $idUnidade, array $linhas): array
    {
        $resultado = [];
        $pode = true;

        foreach ($linhas as $linha) {
            $sit = $this->situacaoFruta($idUnidade, (int) $linha['id_fruta'], (float) $linha['qtd_um']);
            $resultado[] = $sit;
            if (! $sit['ok']) {
                $pode = false;
            }
        }

        return ['pode' => $pode, 'linhas' => $resultado];
    }

    public function saldoUmDisponivel(int $idUnidade, int $idFruta): float
    {
        $posicao = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->first();

        if ($posicao !== null) {
            return max(0.0, round((float) $posicao->qtd_fruta_um, 3));
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->first();

        return $estoque !== null ? max(0.0, round((float) $estoque->qtd_fruta_um, 3)) : 0.0;
    }
}
