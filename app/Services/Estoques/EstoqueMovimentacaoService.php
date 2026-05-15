<?php

namespace App\Services\Estoques;

use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\MovimentacaoEstoque;
use App\Models\UnidadeNegocio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EstoqueMovimentacaoService
{
    public function assertUnidadePermiteEstoque(UnidadeNegocio $unidade): void
    {
        if (! $unidade->possui_estoque) {
            throw new \InvalidArgumentException('Esta unidade de negócio não possui controle de estoque.');
        }
    }

    public function kgPorUnidadeMedicaoSeguro(Fruta $fruta): float
    {
        $kg = (float) $fruta->kg_por_unidade_medicao;

        if ($kg <= 0) {
            throw new \InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
        }

        return $kg;
    }

    /**
     * Movimentação manual: entrada (requer preço) ou saída (mantém preço médio).
     */
    public function movimentarPorTipo(
        UnidadeNegocio $unidade,
        Fruta $fruta,
        string $tipo,
        string $quantidadeKg,
        ?string $precoMedioKgEntrada,
    ): MovimentacaoEstoque {
        $this->assertUnidadePermiteEstoque($unidade);

        $tipoNormalizado = mb_strtolower(trim($tipo));
        if (! in_array($tipoNormalizado, ['entrada', 'saida'], true)) {
            throw new \InvalidArgumentException('Tipo de movimentação inválido.');
        }

        $q = round((float) str_replace(',', '.', $quantidadeKg), 2);
        if ($q <= 0) {
            throw new \InvalidArgumentException('Informe uma quantidade maior que zero.');
        }

        return DB::transaction(function () use (
            $unidade,
            $fruta,
            $tipoNormalizado,
            $q,
            $precoMedioKgEntrada,
        ): MovimentacaoEstoque {
            $estoque = $this->resolverEstoqueComLock($unidade->id, $fruta->id);
            $kgPorUm = $this->kgPorUnidadeMedicaoSeguro($fruta);
            $fruta->refresh();

            $qAtual = (float) $estoque->qtd_fruta_kg;
            $pAtual = (float) $estoque->preco_medio_kg;

            if ($tipoNormalizado === 'entrada') {
                if ($precoMedioKgEntrada === null || trim((string) $precoMedioKgEntrada) === '') {
                    throw new \InvalidArgumentException('Informe o preço médio (kg) para entradas.');
                }
                $pIn = round((float) str_replace(',', '.', $precoMedioKgEntrada), 2);
                if ($pIn < 0) {
                    throw new \InvalidArgumentException('O preço médio (kg) não pode ser negativo.');
                }
                $novaQ = round($qAtual + $q, 2);
                $novaP = $novaQ > 0
                    ? round(($qAtual * $pAtual + $q * $pIn) / $novaQ, 2)
                    : 0.0;
            } else {
                $novaQ = round($qAtual - $q, 2);
                if ($novaQ < 0) {
                    throw new \InvalidArgumentException('Quantidade insuficiente em estoque para esta saída.');
                }
                $novaP = $novaQ > 0 ? $pAtual : 0.0;
            }

            return $this->aplicarNovaPosicao($estoque, $kgPorUm, $novaQ, $novaP);
        });
    }

    /**
     * Importação Excel: define posição consolidada (valores absolutos).
     */
    public function definirPosicaoAbsoluta(
        UnidadeNegocio $unidade,
        Fruta $fruta,
        string $qtdKg,
        string $precoMedioKg,
    ): MovimentacaoEstoque {
        $this->assertUnidadePermiteEstoque($unidade);

        $novaQ = round((float) str_replace(',', '.', $qtdKg), 2);
        $novaP = round((float) str_replace(',', '.', $precoMedioKg), 2);

        if ($novaQ < 0 || $novaP < 0) {
            throw new \InvalidArgumentException('Quantidade e preço não podem ser negativos.');
        }

        return DB::transaction(function () use ($unidade, $fruta, $novaQ, $novaP): MovimentacaoEstoque {
            $estoque = $this->resolverEstoqueComLock($unidade->id, $fruta->id);
            $kgPorUm = $this->kgPorUnidadeMedicaoSeguro($fruta);
            $fruta->refresh();

            return $this->aplicarNovaPosicao($estoque, $kgPorUm, $novaQ, $novaP);
        });
    }

    private function resolverEstoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque !== null) {
            return $estoque;
        }

        try {
            return Estoque::query()->create([
                'id_unidade_negocio' => $idUnidade,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => 0,
                'qtd_fruta_um' => 0,
                'preco_medio_kg' => 0,
                'preco_medio_um' => 0,
                'valor_total_acumulado' => 0,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque === null) {
            throw new \RuntimeException('Não foi possível obter o registro de estoque após concorrência de inserção.');
        }

        return $estoque;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }

    private function aplicarNovaPosicao(
        Estoque $estoque,
        float $kgPorUm,
        float $novaQKg,
        float $novaPKg,
    ): MovimentacaoEstoque {
        $novaQUm = round($novaQKg / $kgPorUm, 2);
        $novaPUm = round($novaPKg * $kgPorUm, 2);
        $valorTotal = round($novaQKg * $novaPKg, 2);

        MovimentacaoEstoque::query()
            ->where('id_estoque', $estoque->id)
            ->where('status_ultima_posicao', true)
            ->update(['status_ultima_posicao' => false]);

        $mov = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $estoque->id_unidade_negocio,
            'id_fruta' => $estoque->id_fruta,
            'qtd_fruta_kg' => number_format($novaQKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($novaQUm, 2, '.', ''),
            'preco_medio_kg' => number_format($novaPKg, 2, '.', ''),
            'preco_medio_um' => number_format($novaPUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorTotal, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoque->forceFill([
            'qtd_fruta_kg' => $mov->qtd_fruta_kg,
            'qtd_fruta_um' => $mov->qtd_fruta_um,
            'preco_medio_kg' => $mov->preco_medio_kg,
            'preco_medio_um' => $mov->preco_medio_um,
            'valor_total_acumulado' => $mov->valor_total_fruta,
        ])->save();

        return $mov;
    }
}
