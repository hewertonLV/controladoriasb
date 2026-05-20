<?php

namespace App\Services\Frutas;

use App\Models\Fruta;
use App\Models\FrutaHistorico;
use App\Models\User;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;

class FrutaAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'nome',
        'unidade_medicao',
        'kg_por_unidade_medicao',
        'procedencia',
    ];

    public function __construct(
        private readonly FrutaIcmsAliquotaResolver $resolver,
    ) {}

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
        $fruta->loadMissing(['icmsAliquotas.estado']);

        $icms = [];
        foreach ($fruta->icmsAliquotas->groupBy('id_estado') as $idEstado => $grupo) {
            $estado = $grupo->first()?->estado;
            $uf = $estado?->abreviacao ?? (string) $idEstado;
            $icms[$uf] = $this->resolver->mapaParaFormulario($fruta, (int) $idEstado);
        }

        ksort($icms);

        return [
            'id_cigam' => $fruta->id_cigam,
            'nome' => $fruta->nome,
            'unidade_medicao' => $fruta->unidade_medicao,
            'kg_por_unidade_medicao' => $this->formatKg($fruta->kg_por_unidade_medicao),
            'procedencia' => $fruta->procedencia,
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
        $ufs = array_unique(array_merge(array_keys($icmsAntes), array_keys($icmsDepois)));
        sort($ufs);

        foreach ($ufs as $uf) {
            $linhaAntes = $icmsAntes[$uf] ?? FrutaIcmsLinhaFormulario::vazia();
            $linhaDepois = $icmsDepois[$uf] ?? FrutaIcmsLinhaFormulario::vazia();

            foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
                if (($linhaAntes[$chave] ?? '') !== ($linhaDepois[$chave] ?? '')) {
                    $alteracoes[] = [
                        'campo' => "icms.{$uf}.{$chave}",
                        'antes' => $linhaAntes[$chave] ?? '',
                        'depois' => $linhaDepois[$chave] ?? '',
                    ];
                }
            }
        }

        return $alteracoes;
    }

    private function formatKg(mixed $value): string
    {
        return number_format(max(0, (float) $value), 2, '.', '');
    }
}
