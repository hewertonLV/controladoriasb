<?php

namespace App\Services\Veiculos;

use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoHistorico;

class VeiculoAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_sbs',
        'nome',
        'tipo',
        'id_unidade_negocio',
        'status',
    ];

    public function registrarCriacao(Veiculo $veiculo, ?User $user, string $origem): VeiculoHistorico
    {
        $acao = $origem === VeiculoHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? VeiculoHistorico::ACAO_IMPORTACAO_CRIACAO
            : VeiculoHistorico::ACAO_CRIACAO;

        return VeiculoHistorico::create([
            'veiculo_id' => $veiculo->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($veiculo),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Veiculo $veiculo,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?VeiculoHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === VeiculoHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? VeiculoHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : VeiculoHistorico::ACAO_ATUALIZACAO;

        return VeiculoHistorico::create([
            'veiculo_id' => $veiculo->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => $antes,
            'dados_depois' => $depois,
            'alteracoes' => $diff,
        ]);
    }

    public function registrarInativacao(Veiculo $veiculo, ?User $user): VeiculoHistorico
    {
        return VeiculoHistorico::create([
            'veiculo_id' => $veiculo->id,
            'user_id' => $user?->id,
            'origem' => VeiculoHistorico::ORIGEM_MANUAL,
            'acao' => VeiculoHistorico::ACAO_INATIVACAO,
            'dados_antes' => null,
            'dados_depois' => null,
            'alteracoes' => [[
                'campo' => 'status',
                'antes' => 'ATIVO',
                'depois' => 'INATIVO',
            ]],
        ]);
    }

    public function registrarReativacao(Veiculo $veiculo, ?User $user): VeiculoHistorico
    {
        return VeiculoHistorico::create([
            'veiculo_id' => $veiculo->id,
            'user_id' => $user?->id,
            'origem' => VeiculoHistorico::ORIGEM_MANUAL,
            'acao' => VeiculoHistorico::ACAO_REATIVACAO,
            'dados_antes' => null,
            'dados_depois' => null,
            'alteracoes' => [[
                'campo' => 'status',
                'antes' => 'INATIVO',
                'depois' => 'ATIVO',
            ]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Veiculo $veiculo): array
    {
        return [
            'id_sbs' => (int) $veiculo->id_sbs,
            'nome' => $veiculo->nome,
            'tipo' => $veiculo->tipo,
            'id_unidade_negocio' => (int) $veiculo->id_unidade_negocio,
            'status' => $veiculo->status,
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

            if (in_array($campo, ['id_sbs', 'id_unidade_negocio'], true)) {
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
