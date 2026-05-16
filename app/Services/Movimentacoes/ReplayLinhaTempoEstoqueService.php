<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Reconstroi a posição de estoque por unidade/fruta considerando entradas e saídas ativas em ordem operacional.
 */
final class ReplayLinhaTempoEstoqueService
{
    public function reprocessarUnidadeFruta(int $idUnidadeNegocio, int $idFruta): void
    {
        $fruta = Fruta::query()->findOrFail($idFruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('Fruta com kg por unidade inválido.');
        }

        $empresaId = $this->empresaIdDaUnidade($idUnidadeNegocio);
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->firstOrFail();

        $eventos = $this->eventos($empresaId, $idFruta);
        if (! $eventos->contains(static fn (array $evento): bool => $evento['tipo'] === 'entrada')) {
            return;
        }

        $ids = $eventos->map(static fn (array $e): int => (int) $e['movimentacao']->id)->sort()->values()->all();
        if ($ids !== []) {
            Movimentacao::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }

        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->update(['status_ultima_posicao' => false]);

        $baseline = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->whereNull('movimentacao_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($baseline === null) {
            $baseline = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $idUnidadeNegocio,
                'id_fruta' => $idFruta,
                'movimentacao_id' => null,
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_fruta' => '0.00',
                'status_ultima_posicao' => false,
            ]);
        }

        $baseline->forceFill([
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => false,
        ])->save();

        $prevMeId = (int) $baseline->id;
        $ultimaMeId = $prevMeId;
        $runUm = 0.0;
        $runKg = 0.0;
        $valorAcumulado = 0.0;

        foreach ($eventos as $evento) {
            /** @var Movimentacao $movimentacao */
            $movimentacao = $evento['movimentacao'];
            $tipo = $evento['tipo'];

            if ($tipo === 'entrada') {
                [$runUm, $runKg, $valorAcumulado, $prevMeId, $ultimaMeId] = $this->aplicarEntrada(
                    $movimentacao,
                    $estoque,
                    $idUnidadeNegocio,
                    $idFruta,
                    $kgPorUm,
                    $runUm,
                    $runKg,
                    $valorAcumulado,
                    $prevMeId,
                );

                continue;
            }

            [$runUm, $runKg, $valorAcumulado, $prevMeId, $ultimaMeId] = $this->aplicarSaida(
                $movimentacao,
                $estoque,
                $idUnidadeNegocio,
                $idFruta,
                $kgPorUm,
                $runUm,
                $runKg,
                $valorAcumulado,
                $prevMeId,
            );
        }

        MovimentacaoEstoque::query()->whereKey($ultimaMeId)->update(['status_ultima_posicao' => true]);

        $precoMedioKg = $runKg > 0 ? round($valorAcumulado / $runKg, 2) : 0.0;
        $precoMedioUm = round($precoMedioKg * $kgPorUm, 2);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($runKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($runUm, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($valorAcumulado, 2, '.', ''),
        ])->save();
    }

    /**
     * @return Collection<int, array{tipo: 'entrada'|'saida', movimentacao: Movimentacao}>
     */
    private function eventos(int $empresaId, int $idFruta): Collection
    {
        $entradasCompra = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_empresa_destino', $empresaId)
            ->where('id_fruta', $idFruta)
            ->get()
            ->map(static fn (Movimentacao $m): array => ['tipo' => 'entrada', 'movimentacao' => $m]);

        $entradasTransferencia = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('id_empresa_destino', $empresaId)
            ->where('id_fruta', $idFruta)
            ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value)
            ->get()
            ->map(static fn (Movimentacao $m): array => ['tipo' => 'entrada', 'movimentacao' => $m]);

        $saidasDoacao = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $empresaId)
            ->where('id_fruta', $idFruta)
            ->get()
            ->map(static fn (Movimentacao $m): array => ['tipo' => 'saida', 'movimentacao' => $m]);

        $saidasDescarte = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $empresaId)
            ->where('id_fruta', $idFruta)
            ->get()
            ->map(static fn (Movimentacao $m): array => ['tipo' => 'saida', 'movimentacao' => $m]);

        $saidasTransferencia = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $empresaId)
            ->where('id_fruta', $idFruta)
            ->get()
            ->map(static fn (Movimentacao $m): array => ['tipo' => 'saida', 'movimentacao' => $m]);

        return $entradasCompra
            ->concat($entradasTransferencia)
            ->concat($saidasDoacao)
            ->concat($saidasDescarte)
            ->concat($saidasTransferencia)
            ->sortBy(static function (array $evento): array {
                /** @var Movimentacao $m */
                $m = $evento['movimentacao'];

                return [
                    (string) $m->data_movimentacao,
                    (int) ($m->movimentacao_origem_id ?? $m->id),
                    (int) $m->versao,
                    (int) $m->id,
                ];
            })
            ->values();
    }

    /**
     * @return array{float, float, float, int, int}
     */
    private function aplicarEntrada(
        Movimentacao $m,
        Estoque $estoque,
        int $idUnidadeNegocio,
        int $idFruta,
        float $kgPorUm,
        float $runUm,
        float $runKg,
        float $valorAcumulado,
        int $prevMeId,
    ): array {
        $qUm = (int) $m->status_movimentacao_id === StatusMovimentacao::ID_ENTRADA && $m->qtd_recebida_um !== null
            ? (float) $m->qtd_recebida_um
            : (float) $m->qtd_fruta_um;
        $qKg = (int) $m->status_movimentacao_id === StatusMovimentacao::ID_ENTRADA && $m->qtd_recebida_kg !== null
            ? (float) $m->qtd_recebida_kg
            : (float) $m->qtd_fruta_kg;

        if ($qUm <= 0 || $qKg <= 0) {
            throw new InvalidArgumentException('Quantidade inválida durante replay integrado de entrada.');
        }

        $runUm = round($runUm + $qUm, 2);
        $runKg = round($runKg + $qKg, 2);
        $valorAcumulado = round($valorAcumulado + ((float) $m->preco_medio_fruta_kg * $qKg), 2);

        $precoMedioKg = $runKg > 0 ? round($valorAcumulado / $runKg, 2) : 0.0;
        $precoMedioUm = round($precoMedioKg * $kgPorUm, 2);

        $me = $this->salvarMovimentacaoEstoque(
            $m,
            $estoque,
            $idUnidadeNegocio,
            $idFruta,
            $runKg,
            $runUm,
            $precoMedioKg,
            $precoMedioUm,
            $valorAcumulado,
        );

        $m->forceFill([
            'id_movimentacao_estoque_old' => $prevMeId,
            'id_movimentacao_estoque_new' => $me->id,
            'saldo_estoque_fruta_kg' => number_format($runKg, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($runUm, 2, '.', ''),
            'versao_replay' => (int) ($m->versao_replay ?? 1) + 1,
        ])->saveQuietly();

        return [$runUm, $runKg, $valorAcumulado, (int) $me->id, (int) $me->id];
    }

    /**
     * @return array{float, float, float, int, int}
     */
    private function aplicarSaida(
        Movimentacao $m,
        Estoque $estoque,
        int $idUnidadeNegocio,
        int $idFruta,
        float $kgPorUm,
        float $runUm,
        float $runKg,
        float $valorAcumulado,
        int $prevMeId,
    ): array {
        $qUm = (float) $m->qtd_fruta_um;
        $qKg = (float) $m->qtd_fruta_kg;

        if ($runUm + 1e-6 < $qUm || $runKg + 1e-6 < $qKg) {
            throw new InvalidArgumentException('Saldo insuficiente durante replay integrado de saída.');
        }

        $precoMedioKg = $runKg > 0 ? round($valorAcumulado / $runKg, 2) : 0.0;
        $precoMedioUm = round($precoMedioKg * $kgPorUm, 2);
        $valorMovimentacao = round($precoMedioKg * $qKg, 2);

        $runUm = round($runUm - $qUm, 2);
        $runKg = round($runKg - $qKg, 2);
        $valorAcumulado = round($valorAcumulado - $valorMovimentacao, 2);

        $me = $this->salvarMovimentacaoEstoque(
            $m,
            $estoque,
            $idUnidadeNegocio,
            $idFruta,
            $runKg,
            $runUm,
            $precoMedioKg,
            $precoMedioUm,
            $valorAcumulado,
        );

        $attrs = [
            'id_movimentacao_estoque_old' => $prevMeId,
            'id_movimentacao_estoque_new' => $me->id,
            'saldo_estoque_fruta_kg' => number_format($runKg, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($runUm, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoMedioUm, 2, '.', ''),
            'versao_replay' => (int) ($m->versao_replay ?? 1) + 1,
        ];

        if (in_array((int) $m->categoria_movimentacao_id, [CategoriaMovimentacaoTipo::Doacao->value, CategoriaMovimentacaoTipo::Descarte->value], true)) {
            $attrs['valor_total_movimentacao'] = number_format($valorMovimentacao, 2, '.', '');
            $attrs['valor_nf_total'] = '0.00';
            $attrs['valor_nf_um'] = '0.00';
            $attrs['valor_nf_kg'] = '0.00';
            $attrs['valor_icms_total'] = '0.00';
            $attrs['valor_icms_kg'] = '0.00';
            $attrs['valor_icms_um'] = '0.00';
            $attrs['icms_convertido_kg'] = '0.00';
        }

        $m->forceFill($attrs)->saveQuietly();

        return [$runUm, $runKg, $valorAcumulado, (int) $me->id, (int) $me->id];
    }

    private function salvarMovimentacaoEstoque(
        Movimentacao $m,
        Estoque $estoque,
        int $idUnidadeNegocio,
        int $idFruta,
        float $qtdKg,
        float $qtdUm,
        float $precoMedioKg,
        float $precoMedioUm,
        float $valorAcumulado,
    ): MovimentacaoEstoque {
        $me = MovimentacaoEstoque::query()->firstOrNew([
            'movimentacao_id' => $m->id,
        ]);

        $me->forceFill([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidadeNegocio,
            'id_fruta' => $idFruta,
            'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorAcumulado, 2, '.', ''),
            'status_ultima_posicao' => false,
        ])->save();

        return $me;
    }

    private function empresaIdDaUnidade(int $idUnidadeNegocio): int
    {
        $empresa = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $idUnidadeNegocio)
            ->firstOrFail();

        return (int) $empresa->id;
    }
}
