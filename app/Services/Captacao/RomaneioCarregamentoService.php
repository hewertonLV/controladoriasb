<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use Illuminate\Support\Collection;

final class RomaneioCarregamentoService
{
    /**
     * @return Collection<int, array{
     *     id_cliente: int,
     *     cliente_nome: string,
     *     id_captacao_rota: int|null,
     *     rota_nome: string|null,
     *     itens: list<array{
     *         id_fruta: int,
     *         fruta_nome: string,
     *         unidade_medicao: string,
     *         quantidade_um: string,
     *         quantidade_um_formatado: string,
     *         quantidade_kg: string,
     *         quantidade_kg_formatado: string,
     *     }>,
     *     total_kg: string,
     *     total_kg_formatado: string,
     *     totais_por_um: list<array{unidade_medicao: string, quantidade: string, quantidade_formatado: string}>,
     * }>
     */
    public function preview(CaptacaoLote $lote): Collection
    {
        $lote->load([
            'pedidos.cliente:id,razao_social,fantasia',
            'pedidos.rota:id,nome',
            'pedidos.itens.fruta:id,nome,unidade_medicao,kg_por_unidade_medicao',
        ]);

        return $lote->pedidos
            ->map(function ($pedido): ?array {
                $itens = $pedido->itens
                    ->map(fn ($item) => $this->mapItem($item))
                    ->filter()
                    ->values()
                    ->all();

                if ($itens === []) {
                    return null;
                }

                return [
                    'id_cliente' => $pedido->id_cliente,
                    'cliente_nome' => $pedido->cliente->fantasia ?: $pedido->cliente->razao_social,
                    'id_captacao_rota' => $pedido->id_captacao_rota,
                    'rota_nome' => $pedido->rota?->nome,
                    'itens' => $itens,
                    ...$this->calcularTotaisLoja($itens),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lojas
     * @return array{
     *     total_kg: string,
     *     total_kg_formatado: string,
     *     totais_por_um: list<array{unidade_medicao: string, quantidade: string, quantidade_formatado: string}>,
     * }
     */
    public function totaisGerais(Collection $lojas): array
    {
        /** @var list<array{unidade_medicao: string, quantidade_um: string, quantidade_kg: string}> $todosItens */
        $todosItens = $lojas
            ->flatMap(fn (array $loja) => $loja['itens'])
            ->values()
            ->all();

        if ($todosItens === []) {
            return [
                'total_kg' => $this->formatarNumero(0, 2),
                'total_kg_formatado' => $this->formatarBr(0, 2),
                'totais_por_um' => [],
            ];
        }

        return $this->calcularTotaisLoja($todosItens);
    }

    /**
     * @return array{
     *     id_fruta: int,
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     quantidade_um: string,
     *     quantidade_um_formatado: string,
     *     quantidade_kg: string,
     *     quantidade_kg_formatado: string,
     * }|null
     */
    private function mapItem($item): ?array
    {
        $qtdUm = (float) $item->quantidade;
        if ($qtdUm <= 0) {
            return null;
        }

        $fruta = $item->fruta;
        $casasKg = $fruta->casasDecimaisKgPorUnidadeMedicao();
        $qtdKg = round($qtdUm * (float) $fruta->kg_por_unidade_medicao, $casasKg);
        $unidadeMedicao = mb_strtoupper(trim((string) $fruta->unidade_medicao), 'UTF-8');

        return [
            'id_fruta' => $item->id_fruta,
            'fruta_nome' => $fruta->nome,
            'unidade_medicao' => $unidadeMedicao,
            'quantidade_um' => $this->formatarNumero($qtdUm, 2),
            'quantidade_um_formatado' => $this->formatarBr($qtdUm, 2),
            'quantidade_kg' => $this->formatarNumero($qtdKg, $casasKg),
            'quantidade_kg_formatado' => $this->formatarBr($qtdKg, $casasKg),
        ];
    }

    /**
     * @param  list<array{unidade_medicao: string, quantidade_um: string, quantidade_kg: string}>  $itens
     * @return array{
     *     total_kg: string,
     *     total_kg_formatado: string,
     *     totais_por_um: list<array{unidade_medicao: string, quantidade: string, quantidade_formatado: string}>,
     * }
     */
    private function calcularTotaisLoja(array $itens): array
    {
        $totalKg = 0.0;
        $casasKgMax = 2;
        /** @var array<string, float> $somaPorUm */
        $somaPorUm = [];

        foreach ($itens as $item) {
            $totalKg += (float) $item['quantidade_kg'];

            $partesKg = explode('.', $item['quantidade_kg']);
            $casasKgMax = max($casasKgMax, strlen($partesKg[1] ?? ''));

            $um = $item['unidade_medicao'];
            $somaPorUm[$um] = ($somaPorUm[$um] ?? 0.0) + (float) $item['quantidade_um'];
        }

        $totalKg = round($totalKg, $casasKgMax);

        $totaisPorUm = collect($somaPorUm)
            ->map(fn (float $qtd, string $um) => [
                'unidade_medicao' => $um,
                'quantidade' => $this->formatarNumero($qtd, 2),
                'quantidade_formatado' => $this->formatarBr($qtd, 2),
            ])
            ->sortBy('unidade_medicao')
            ->values()
            ->all();

        return [
            'total_kg' => $this->formatarNumero($totalKg, $casasKgMax),
            'total_kg_formatado' => $this->formatarBr($totalKg, $casasKgMax),
            'totais_por_um' => $totaisPorUm,
        ];
    }

    private function formatarNumero(float $valor, int $casas): string
    {
        return number_format($valor, $casas, '.', '');
    }

    private function formatarBr(float $valor, int $casas): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}
