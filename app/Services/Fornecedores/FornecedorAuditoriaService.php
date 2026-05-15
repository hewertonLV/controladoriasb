<?php

namespace App\Services\Fornecedores;

use App\Models\Fornecedor;
use App\Models\FornecedorHistorico;
use App\Models\User;

class FornecedorAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'id_estado',
        'razao_social',
        'fantasia',
        'cnpj_cpf',
    ];

    public function registrarCriacao(Fornecedor $fornecedor, ?User $user, string $origem): FornecedorHistorico
    {
        $acao = $origem === FornecedorHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FornecedorHistorico::ACAO_IMPORTACAO_CRIACAO
            : FornecedorHistorico::ACAO_CRIACAO;

        return FornecedorHistorico::create([
            'fornecedor_id' => $fornecedor->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($fornecedor),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Fornecedor $fornecedor,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?FornecedorHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === FornecedorHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FornecedorHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : FornecedorHistorico::ACAO_ATUALIZACAO;

        return FornecedorHistorico::create([
            'fornecedor_id' => $fornecedor->id,
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
    public function snapshot(Fornecedor $fornecedor): array
    {
        return [
            'id_cigam' => $fornecedor->id_cigam,
            'id_estado' => (int) $fornecedor->id_estado,
            'razao_social' => $fornecedor->razao_social,
            'fantasia' => $fornecedor->fantasia,
            'cnpj_cpf' => $fornecedor->cnpj_cpf,
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

            if ($campo === 'id_estado') {
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
