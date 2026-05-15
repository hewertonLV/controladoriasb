<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\ClienteHistorico;
use App\Models\User;

class ClienteAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'razao_social',
        'cnpj_cpf',
        'id_unidade_negocio',
        'id_praca',
        'grupo_id',
        'desconto_nf',
        'desconto_contrato',
    ];

    public function registrarCriacao(Cliente $cliente, ?User $user, string $origem): ClienteHistorico
    {
        $acao = $origem === ClienteHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? ClienteHistorico::ACAO_IMPORTACAO_CRIACAO
            : ClienteHistorico::ACAO_CRIACAO;

        return ClienteHistorico::create([
            'cliente_id' => $cliente->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($cliente),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Cliente $cliente,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?ClienteHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === ClienteHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? ClienteHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : ClienteHistorico::ACAO_ATUALIZACAO;

        return ClienteHistorico::create([
            'cliente_id' => $cliente->id,
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
    public function snapshot(Cliente $cliente): array
    {
        return [
            'id_cigam' => $cliente->id_cigam,
            'razao_social' => $cliente->razao_social,
            'cnpj_cpf' => $cliente->cnpj_cpf,
            'id_unidade_negocio' => (int) $cliente->id_unidade_negocio,
            'id_praca' => (int) $cliente->id_praca,
            'grupo_id' => $cliente->grupo_id !== null ? (int) $cliente->grupo_id : null,
            'desconto_nf' => (string) $cliente->desconto_nf,
            'desconto_contrato' => (string) $cliente->desconto_contrato,
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

            if (in_array($campo, ['id_unidade_negocio', 'id_praca'], true)) {
                $a = (int) $valorAntes;
                $b = (int) $valorDepois;
            } elseif ($campo === 'grupo_id') {
                $a = $valorAntes === null ? null : (int) $valorAntes;
                $b = $valorDepois === null ? null : (int) $valorDepois;
            } elseif (in_array($campo, ['desconto_nf', 'desconto_contrato'], true)) {
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
