<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\EmpresaHistorico;
use App\Models\User;

/**
 * Auditoria do hub de empresas (vínculo corporativo + snapshot derivado da entidade).
 */
class EmpresaAuditoriaService
{
    /**
     * @var list<string>
     */
    private const COMPARABLE_FIELDS = [
        'tipo_registro',
        'id_cigam',
        'nome_exibicao',
        'fantasia',
        'documento',
        'unidade_referencia',
        'tipo_pessoa',
        'status',
    ];

    public function registrarCriacao(Empresa $empresa, ?User $user, string $origem): EmpresaHistorico
    {
        $acao = match ($origem) {
            EmpresaHistorico::ORIGEM_IMPORTACAO_EXCEL => EmpresaHistorico::ACAO_IMPORTACAO_CRIACAO,
            default => EmpresaHistorico::ACAO_CRIACAO,
        };

        return EmpresaHistorico::create([
            'empresa_id' => $empresa->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($empresa),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Empresa $empresa,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?EmpresaHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === EmpresaHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? EmpresaHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : EmpresaHistorico::ACAO_ATUALIZACAO;

        return EmpresaHistorico::create([
            'empresa_id' => $empresa->id,
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
    public function snapshot(Empresa $empresa): array
    {
        return $empresa->dadosConsolidadosParaAuditoria();
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

            if ($campo === 'status') {
                $a = (bool) $valorAntes;
                $b = (bool) $valorDepois;
            } elseif ($campo === 'fantasia') {
                $a = $valorAntes === null || $valorAntes === '' ? null : (string) $valorAntes;
                $b = $valorDepois === null || $valorDepois === '' ? null : (string) $valorDepois;
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
