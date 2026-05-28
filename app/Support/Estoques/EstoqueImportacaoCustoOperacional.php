<?php

namespace App\Support\Estoques;

use App\Models\UnidadeNegocio;
use InvalidArgumentException;

final class EstoqueImportacaoCustoOperacional
{
    public static function resolverCustoOperacionalKg(UnidadeNegocio $unidade): float
    {
        $vigente = $unidade->relationLoaded('historicoCustoOperacionalAtual')
            ? $unidade->historicoCustoOperacionalAtual
            : $unidade->historicoCustoOperacionalAtual()->first();

        if ($vigente !== null) {
            return round((float) $vigente->custo_operacional, 2);
        }

        return round((float) $unidade->custo_operacional, 2);
    }

    /**
     * @return array{custo_operacional_kg: string}
     */
    public static function metadadosPreview(UnidadeNegocio $unidade): array
    {
        return [
            'custo_operacional_kg' => number_format(self::resolverCustoOperacionalKg($unidade), 2, '.', ''),
        ];
    }

    public static function precoMedioKgAplicandoCo(
        string $precoMedioKgBase,
        UnidadeNegocio $unidade,
        bool $aplicarCo,
        ?string $qtdFrutaKg = null,
        ?string $qtdFrutaUm = null,
    ): string {
        if ($qtdFrutaKg !== null && $qtdFrutaUm !== null && self::quantidadeEstoqueZerada($qtdFrutaKg, $qtdFrutaUm)) {
            return '0.00';
        }

        $precoBase = round((float) $precoMedioKgBase, 2);

        if (! $aplicarCo) {
            return number_format($precoBase, 2, '.', '');
        }

        $custoKg = self::resolverCustoOperacionalKg($unidade);

        return number_format(round($precoBase + $custoKg, 2), 2, '.', '');
    }

    public static function quantidadeEstoqueZerada(string $qtdFrutaKg, string $qtdFrutaUm): bool
    {
        return abs(round((float) str_replace(',', '.', $qtdFrutaKg), 2)) < 0.005
            || abs(round((float) str_replace(',', '.', $qtdFrutaUm), 2)) < 0.005;
    }

    public static function precoMedioKgAplicandoCoPorId(
        string $precoMedioKgBase,
        int $idUnidadeNegocio,
        bool $aplicarCo,
    ): string {
        $unidade = UnidadeNegocio::query()
            ->with('historicoCustoOperacionalAtual')
            ->find($idUnidadeNegocio);

        if ($unidade === null) {
            throw new InvalidArgumentException('Unidade de negócio não encontrada.');
        }

        return self::precoMedioKgAplicandoCo($precoMedioKgBase, $unidade, $aplicarCo);
    }

    /**
     * Garante `custo_operacional_kg` em prévias antigas ou geradas antes do deploy do CO.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public static function enriquecerCoNoPreviewResultado(array $resultado): array
    {
        $unidadeIds = self::coletarIdsUnidadeNegocioDoPreview($resultado);
        if ($unidadeIds === []) {
            return $resultado;
        }

        $coPorUnidadeId = self::mapaCustoOperacionalKgPorUnidadeId($unidadeIds);

        if (isset($resultado['novas']) && is_array($resultado['novas'])) {
            $resultado['novas'] = self::aplicarCoNasLinhasPreview($resultado['novas'], 'dados', $coPorUnidadeId);
        }

        if (isset($resultado['atualizacoes']) && is_array($resultado['atualizacoes'])) {
            $resultado['atualizacoes'] = self::aplicarCoNasLinhasPreview(
                $resultado['atualizacoes'],
                'dados_novos',
                $coPorUnidadeId,
            );
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<int>
     */
    private static function coletarIdsUnidadeNegocioDoPreview(array $resultado): array
    {
        $ids = [];

        foreach (['novas', 'atualizacoes'] as $secao) {
            if (! isset($resultado[$secao]) || ! is_array($resultado[$secao])) {
                continue;
            }

            foreach ($resultado[$secao] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (int) ($item['id_unidade_negocio'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $unidadeIds
     * @return array<int, string>
     */
    private static function mapaCustoOperacionalKgPorUnidadeId(array $unidadeIds): array
    {
        $unidades = UnidadeNegocio::query()
            ->with('historicoCustoOperacionalAtual')
            ->whereIn('id', $unidadeIds)
            ->get();

        $mapa = [];
        foreach ($unidades as $unidade) {
            $mapa[$unidade->id] = number_format(self::resolverCustoOperacionalKg($unidade), 2, '.', '');
        }

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<int, string>  $coPorUnidadeId
     * @return list<array<string, mixed>>
     */
    private static function aplicarCoNasLinhasPreview(array $linhas, string $campoDados, array $coPorUnidadeId): array
    {
        foreach ($linhas as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $idUnidade = (int) ($item['id_unidade_negocio'] ?? 0);
            if ($idUnidade <= 0 || ! isset($coPorUnidadeId[$idUnidade])) {
                continue;
            }

            $dados = $item[$campoDados] ?? null;
            if (! is_array($dados)) {
                $dados = [];
            }

            $dados['custo_operacional_kg'] = $coPorUnidadeId[$idUnidade];
            $item[$campoDados] = $dados;
            $linhas[$index] = $item;
        }

        return $linhas;
    }
}
