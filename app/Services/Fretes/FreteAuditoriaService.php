<?php

namespace App\Services\Fretes;

use App\Models\Frete;
use App\Models\FreteHistorico;
use App\Models\User;
use App\Support\TextoCadastro;

class FreteAuditoriaService
{
    private const COMPARABLE_FIELDS = [
        'nome',
        'valor',
        'id_veiculo',
        'descricao',
        'status_situacao',
        'valor_fruta_kg',
    ];

    public function registrarCriacao(Frete $frete, ?User $user, string $origem): FreteHistorico
    {
        $acao = $origem === FreteHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FreteHistorico::ACAO_IMPORTACAO_CRIACAO
            : FreteHistorico::ACAO_CRIACAO;

        return FreteHistorico::create([
            'frete_id' => $frete->id,
            'user_id' => $user?->id,
            'origem' => $origem,
            'acao' => $acao,
            'dados_antes' => null,
            'dados_depois' => $this->snapshot($frete),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    public function registrarAtualizacao(
        Frete $frete,
        array $antes,
        array $depois,
        ?User $user,
        string $origem,
        ?string $acao = null,
    ): ?FreteHistorico {
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        $acao ??= $origem === FreteHistorico::ORIGEM_IMPORTACAO_EXCEL
            ? FreteHistorico::ACAO_IMPORTACAO_ATUALIZACAO
            : FreteHistorico::ACAO_ATUALIZACAO;

        return FreteHistorico::create([
            'frete_id' => $frete->id,
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
    public function snapshot(Frete $frete): array
    {
        return [
            'nome' => $frete->nome,
            'valor' => $this->formatDecimal($frete->valor),
            'id_veiculo' => (int) $frete->id_veiculo,
            'descricao' => $frete->descricao,
            'status_situacao' => $frete->status_situacao,
            'valor_fruta_kg' => $this->formatDecimal($frete->valor_fruta_kg),
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

            if (in_array($campo, ['valor', 'valor_fruta_kg'], true)) {
                $a = $this->formatDecimal($valorAntes);
                $b = $this->formatDecimal($valorDepois);
            } elseif ($campo === 'id_veiculo') {
                $a = (int) $valorAntes;
                $b = (int) $valorDepois;
            } elseif ($campo === 'descricao') {
                $a = $valorAntes === null || $valorAntes === '' ? '' : (string) $valorAntes;
                $b = $valorDepois === null || $valorDepois === '' ? '' : (string) $valorDepois;
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

    private function formatDecimal(mixed $value): string
    {
        return TextoCadastro::normalizarDecimalNaoNegativo($value);
    }
}
