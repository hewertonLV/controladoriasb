<?php

namespace App\Services\Pracas;

use App\Models\Praca;
use App\Models\PracaHistorico;
use App\Models\User;

class PracaAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'nome',
        'id_unidade_negocio',
    ];

    public function registrarCriacao(Praca $praca, ?User $user, string $origem): PracaHistorico
    {
        $acao = $origem === PracaHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? PracaHistorico::ACAO_IMPORTACAO_CRIACAO
            : PracaHistorico::ACAO_CRIACAO;

        return PracaHistorico::create([
            'praca_id' => $praca->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($praca),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Praca $praca,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?PracaHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === PracaHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? PracaHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : PracaHistorico::ACAO_ATUALIZACAO;

        return PracaHistorico::create([
            'praca_id' => $praca->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => $antes,
            'dados_depois' => $depois,
            'alteracoes' => $diff,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Praca $praca): array
    {
        return [
            'nome' => $praca->nome,
            'id_unidade_negocio' => (int) $praca->id_unidade_negocio,
        ];
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return list<array{campo:string, antes:mixed, depois:mixed}>
     */
    public function diff(array $antes, array $depois): array
    {
        $alteracoes = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            $valorAntes = $antes[$campo] ?? null;
            $valorDepois = $depois[$campo] ?? null;

            if (in_array($campo, ['id_unidade_negocio'], true)) {
                $a = (int) $valorAntes;
                $b = (int) $valorDepois;
            } else {
                $a = (string) ($valorAntes ?? '');
                $b = (string) ($valorDepois ?? '');
            }

            if ($a !== $b) {
                $alteracoes[] = [
                    'campo' => $campo,
                    'antes' => $valorAntes,
                    'depois' => $valorDepois,
                ];
            }
        }

        return $alteracoes;
    }
}
