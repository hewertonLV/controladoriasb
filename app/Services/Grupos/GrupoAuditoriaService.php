<?php

namespace App\Services\Grupos;

use App\Models\Grupo;
use App\Models\GrupoHistorico;
use App\Models\User;

class GrupoAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'nome',
    ];

    public function registrarCriacao(Grupo $grupo, ?User $user, string $origem): GrupoHistorico
    {
        $acao = $origem === GrupoHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? GrupoHistorico::ACAO_IMPORTACAO_CRIACAO
            : GrupoHistorico::ACAO_CRIACAO;

        return GrupoHistorico::create([
            'grupo_id' => $grupo->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($grupo),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Grupo $grupo,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?GrupoHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === GrupoHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? GrupoHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : GrupoHistorico::ACAO_ATUALIZACAO;

        return GrupoHistorico::create([
            'grupo_id' => $grupo->id,
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
    public function snapshot(Grupo $grupo): array
    {
        return [
            'nome' => $grupo->nome,
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

            if ((string) ($valorAntes ?? '') !== (string) ($valorDepois ?? '')) {
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
