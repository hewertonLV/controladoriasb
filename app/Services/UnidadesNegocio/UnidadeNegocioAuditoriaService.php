<?php

namespace App\Services\UnidadesNegocio;

use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioHistorico;
use App\Models\User;

class UnidadeNegocioAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'razao_social',
        'nome',
        'cpf_cnpj',
        'custo_operacional',
        'id_estado',
        'status',
        'possui_estoque',
    ];

    public function registrarCriacao(UnidadeNegocio $unidade, ?User $user, string $origem): UnidadeNegocioHistorico
    {
        $acao = $origem === UnidadeNegocioHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? UnidadeNegocioHistorico::ACAO_IMPORTACAO_CRIACAO
            : UnidadeNegocioHistorico::ACAO_CRIACAO;

        return UnidadeNegocioHistorico::create([
            'unidade_negocio_id' => $unidade->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($unidade),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        UnidadeNegocio $unidade,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?UnidadeNegocioHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === UnidadeNegocioHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? UnidadeNegocioHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : UnidadeNegocioHistorico::ACAO_ATUALIZACAO;

        return UnidadeNegocioHistorico::create([
            'unidade_negocio_id' => $unidade->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => $antes,
            'dados_depois' => $depois,
            'alteracoes' => $diff,
        ]);
    }

    public function registrarInativacao(UnidadeNegocio $unidade, ?User $user): UnidadeNegocioHistorico
    {
        return UnidadeNegocioHistorico::create([
            'unidade_negocio_id' => $unidade->id,
            'user_id' => $user?->id,
            'origem' => UnidadeNegocioHistorico::ORIGEM_MANUAL,
            'acao' => UnidadeNegocioHistorico::ACAO_INATIVACAO,
            'dados_antes' => null,
            'dados_depois' => null,
            'alteracoes' => [[
                'campo' => 'status',
                'antes' => true,
                'depois' => false,
            ]],
        ]);
    }

    public function registrarReativacao(UnidadeNegocio $unidade, ?User $user): UnidadeNegocioHistorico
    {
        return UnidadeNegocioHistorico::create([
            'unidade_negocio_id' => $unidade->id,
            'user_id' => $user?->id,
            'origem' => UnidadeNegocioHistorico::ORIGEM_MANUAL,
            'acao' => UnidadeNegocioHistorico::ACAO_REATIVACAO,
            'dados_antes' => null,
            'dados_depois' => null,
            'alteracoes' => [[
                'campo' => 'status',
                'antes' => false,
                'depois' => true,
            ]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(UnidadeNegocio $unidade): array
    {
        return [
            'id_cigam' => $unidade->id_cigam,
            'razao_social' => $unidade->razao_social,
            'nome' => $unidade->nome,
            'cpf_cnpj' => $unidade->cpf_cnpj,
            'custo_operacional' => (string) $unidade->custo_operacional,
            'id_estado' => (int) $unidade->id_estado,
            'status' => (bool) $unidade->status,
            'possui_estoque' => (bool) $unidade->possui_estoque,
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

            if ($campo === 'status' || $campo === 'possui_estoque') {
                $a = (bool) $valorAntes;
                $b = (bool) $valorDepois;
            } elseif ($campo === 'id_estado') {
                $a = (int) $valorAntes;
                $b = (int) $valorDepois;
            } elseif ($campo === 'custo_operacional') {
                $a = number_format((float) $valorAntes, 2, '.', '');
                $b = number_format((float) $valorDepois, 2, '.', '');
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
