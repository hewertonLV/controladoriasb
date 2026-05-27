<?php

namespace App\Services\Captacao;

use App\Enums\FreteStatusSituacao;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\VendaNota;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CaptacaoLoteFreteService
{
    public function __construct(
        private readonly VendaMovimentacaoService $vendas,
    ) {}

    /**
     * @return array{
     *     transferencias: Collection<int, array{vinculo: CaptacaoLoteMovimentacao, saida: Movimentacao|null, id_frete_atual: int|null}>,
     *     fretesAbertos: Collection<int, Frete>,
     * }
     */
    public function dadosFreteHub(CaptacaoLote $lote): array
    {
        $vinculos = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->whereNotNull('transferencia_origem_id')
            ->with('fruta:id,nome')
            ->get();

        $transferencias = $vinculos->map(function (CaptacaoLoteMovimentacao $vinculo) {
            $saida = Movimentacao::query()
                ->where('transferencia_origem_id', $vinculo->transferencia_origem_id)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->orderByDesc('id')
                ->first();

            return [
                'vinculo' => $vinculo,
                'saida' => $saida,
                'id_frete_atual' => $saida?->id_frete,
            ];
        });

        return [
            'transferencias' => $transferencias,
            'fretesAbertos' => $this->fretesAbertos(),
        ];
    }

    /**
     * @return array{
     *     lojas: Collection<int, array{
     *         id_cliente: int,
     *         loja_nome: string,
     *         numero_nf: string,
     *         id_frete_atual: int|null,
     *         itens: list<array{fruta_nome: string, quantidade: string, unidade_medicao: string|null, preco_venda: string|null}>,
     *     }>,
     *     fretesAbertos: Collection<int, Frete>,
     * }
     */
    public function dadosFreteVendas(CaptacaoLote $lote): array
    {
        $lote->loadMissing(['pedidos.cliente', 'pedidos.itens.fruta']);

        $lojas = collect();
        foreach ($lote->pedidos as $pedido) {
            $itensVendidos = $pedido->itens
                ->filter(static fn ($item) => (float) $item->quantidade > 0)
                ->values();

            if ($itensVendidos->isEmpty()) {
                continue;
            }

            $numeroNf = $this->numeroNfCaptacao($lote, (int) $pedido->id_cliente);
            $nota = VendaNota::query()->where('numero_nf', $numeroNf)->first();
            if ($nota === null) {
                continue;
            }

            $idFreteAtual = Movimentacao::query()
                ->where('venda_nota_id', $nota->id)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->value('id_frete');

            $cliente = $pedido->cliente;
            $lojaNome = $cliente?->fantasia ?: $cliente?->razao_social ?: "Cliente #{$pedido->id_cliente}";

            $lojas->push([
                'id_cliente' => (int) $pedido->id_cliente,
                'loja_nome' => $lojaNome,
                'numero_nf' => $numeroNf,
                'id_frete_atual' => $idFreteAtual !== null ? (int) $idFreteAtual : null,
                'itens' => $itensVendidos->map(static fn ($item) => [
                    'fruta_nome' => $item->fruta?->nome ?? '—',
                    'quantidade' => number_format((float) $item->quantidade, 2, '.', ''),
                    'unidade_medicao' => $item->fruta?->unidade_medicao,
                    'preco_venda' => $item->preco_venda !== null
                        ? number_format((float) $item->preco_venda, 2, '.', '')
                        : null,
                ])->all(),
            ]);
        }

        return [
            'lojas' => $lojas->sortBy('loja_nome')->values(),
            'fretesAbertos' => $this->fretesAbertos(),
        ];
    }

    public function vincularFreteVendaLoja(CaptacaoLote $lote, int $idCliente, ?int $idFrete): void
    {
        $pedido = $lote->pedidos()->where('id_cliente', $idCliente)->first();
        if ($pedido === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Loja não pertence a este lote.',
            ]);
        }

        $numeroNf = $this->numeroNfCaptacao($lote, $idCliente);
        if (! VendaNota::query()->where('numero_nf', $numeroNf)->exists()) {
            throw new InvalidArgumentException('Venda ainda não foi gerada para esta loja.');
        }

        $this->vendas->vincularFreteNotaCaptacao($numeroNf, $idFrete);
    }

    /** @deprecated Use dadosFreteHub() */
    public function dadosIndex(CaptacaoLote $lote): array
    {
        return $this->dadosFreteHub($lote);
    }

    private function numeroNfCaptacao(CaptacaoLote $lote, int $idCliente): string
    {
        return sprintf(
            'CAP-%s-%d-%d',
            $lote->data_referencia->format('Ymd'),
            $lote->id,
            $idCliente,
        );
    }

    /**
     * @return Collection<int, Frete>
     */
    private function fretesAbertos(): Collection
    {
        return Frete::query()
            ->where('status_situacao', FreteStatusSituacao::ABERTA->value)
            ->orderBy('nome')
            ->get(['id', 'nome', 'valor']);
    }
}
