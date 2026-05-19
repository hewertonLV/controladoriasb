<?php

namespace App\Services\Frutas;

use App\Models\Fruta;
use App\Models\FrutaHistorico;
use App\Models\User;

class FrutaAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'nome',
        'unidade_medicao',
        'kg_por_unidade_medicao',
    ];

    public function registrarCriacao(Fruta $fruta, ?User $user, string $origem): FrutaHistorico
    {
        $acao = $origem === FrutaHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FrutaHistorico::ACAO_IMPORTACAO_CRIACAO
            : FrutaHistorico::ACAO_CRIACAO;

        return FrutaHistorico::create([
            'fruta_id' => $fruta->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($fruta),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Fruta $fruta,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?FrutaHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === FrutaHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FrutaHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : FrutaHistorico::ACAO_ATUALIZACAO;

        return FrutaHistorico::create([
            'fruta_id' => $fruta->id,
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
    public function snapshot(Fruta $fruta): array
    {
        $fruta->loadMissing(['icms.estado']);

        $icms = [];
        foreach ($fruta->icms as $registro) {
            $uf = $registro->estado?->abreviacao ?? (string) $registro->id_estado;
            $chave = $uf.'_'.$registro->operacao->value;
            $icms[$chave] = [
                'estado' => $registro->estado?->nome,
                'operacao' => $registro->operacao->value,
                'icms_externo' => $this->formatDecimal($registro->icms_externo),
                'um_icms_externo' => (string) $registro->um_icms_externo,
                'icms_nacional' => $this->formatDecimal($registro->icms_nacional),
                'um_icms_nacional' => (string) $registro->um_icms_nacional,
                'icms_venda_importada' => $this->formatDecimal($registro->icms_venda_importada),
                'um_icms_venda_importada' => (string) $registro->um_icms_venda_importada,
                'icms_venda_nacional' => $this->formatDecimal($registro->icms_venda_nacional),
                'um_icms_venda_nacional' => (string) $registro->um_icms_venda_nacional,
            ];
        }

        ksort($icms);

        return [
            'id_cigam' => $fruta->id_cigam,
            'nome' => $fruta->nome,
            'unidade_medicao' => $fruta->unidade_medicao,
            'kg_por_unidade_medicao' => $this->formatKg($fruta->kg_por_unidade_medicao),
            'icms_por_estado' => $icms,
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

            if ($campo === 'kg_por_unidade_medicao') {
                $a = $this->formatKg($valorAntes);
                $b = $this->formatKg($valorDepois);
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

        $icmsAntes = $antes['icms_por_estado'] ?? [];
        $icmsDepois = $depois['icms_por_estado'] ?? [];

        if ($icmsAntes !== $icmsDepois) {
            $alteracoes[] = [
                'campo' => 'icms_por_estado',
                'antes' => $icmsAntes,
                'depois' => $icmsDepois,
            ];
        }

        return $alteracoes;
    }

    private function formatDecimal(mixed $value): string
    {
        return number_format(max(0, (float) $value), 2, '.', '');
    }

    private function formatKg(mixed $value): string
    {
        return number_format(max(0, (float) $value), 2, '.', '');
    }
}
