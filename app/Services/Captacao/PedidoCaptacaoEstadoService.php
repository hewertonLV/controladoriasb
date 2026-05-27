<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use Illuminate\Support\Collection;

final class PedidoCaptacaoEstadoService
{
    public const ESTADO_NAO_INICIADO = 'nao_iniciado';

    public const ESTADO_EM_ANDAMENTO = 'em_andamento';

    public const ESTADO_CONCLUIDO = 'concluido';

    public function __construct(
        private readonly CaptacaoPrecificacaoService $precificacao,
    ) {}

    /**
     * Todas as lojas da carteira (ou faturamento do lote) para captação por loja.
     *
     * @return Collection<int, Cliente>
     */
    public function lojasDaCarteira(CaptacaoLote $lote): Collection
    {
        $query = Cliente::query()->orderBy('razao_social');

        if ($lote->id_captacao_carteira !== null) {
            $query->where('id_captacao_carteira', $lote->id_captacao_carteira);
        } else {
            $query->where('id_unidade_negocio', $lote->id_unidade_negocio_faturamento);
        }

        return $query
            ->withCount([
                'frutaVinculos as frutas_vinculadas_count' => fn ($q) => $q->where('ativo', true),
            ])
            ->get(['id', 'razao_social', 'fantasia', 'percentual_margem_alvo', 'desconto_nf']);
    }

    /**
     * Lojas com ao menos uma fruta vinculada (exigência para finalizar captação e concluir com quantidade).
     *
     * @return Collection<int, Cliente>
     */
    public function lojasComFrutasVinculadas(CaptacaoLote $lote): Collection
    {
        return $this->lojasDaCarteira($lote)
            ->filter(fn (Cliente $cliente) => $cliente->frutaVinculos()
                ->where('ativo', true)
                ->exists())
            ->values();
    }

    public function clientePossuiFrutasVinculadas(Cliente $cliente): bool
    {
        if (isset($cliente->frutas_vinculadas_count)) {
            return (int) $cliente->frutas_vinculadas_count > 0;
        }

        return $cliente->frutaVinculos()->where('ativo', true)->exists();
    }

    /** @deprecated Use lojasDaCarteira() ou lojasComFrutasVinculadas() */
    public function lojasElegiveis(CaptacaoLote $lote): Collection
    {
        return $this->lojasComFrutasVinculadas($lote);
    }

    /**
     * @return array{
     *     estado: string,
     *     captacao_concluida: bool,
     *     tem_digitacao: bool,
     *     rentabilidade: array{margem_total: string|null, margem_percentual: string|null, faturamento: string},
     * }
     */
    public function estadoLoja(CaptacaoLote $lote, Cliente $cliente, ?Pedido $pedido = null): array
    {
        $pedido ??= $lote->pedidos->firstWhere('id_cliente', $cliente->id);
        $temDigitacao = $pedido !== null && $this->pedidoTemQuantidade($pedido);
        $descontoNf = (float) $cliente->desconto_nf;

        if ($pedido !== null && $pedido->captacao_concluida) {
            return [
                'estado' => self::ESTADO_CONCLUIDO,
                'captacao_concluida' => true,
                'tem_digitacao' => $temDigitacao,
                'rentabilidade' => $this->precificacao->rentabilidadePedido($pedido->itens, $descontoNf),
            ];
        }

        if ($temDigitacao) {
            return [
                'estado' => self::ESTADO_EM_ANDAMENTO,
                'captacao_concluida' => false,
                'tem_digitacao' => true,
                'rentabilidade' => $this->precificacao->rentabilidadePedido($pedido->itens, $descontoNf),
            ];
        }

        return [
            'estado' => self::ESTADO_NAO_INICIADO,
            'captacao_concluida' => false,
            'tem_digitacao' => false,
            'rentabilidade' => [
                'margem_total' => null,
                'margem_percentual' => null,
                'faturamento' => '0.00',
            ],
        ];
    }

    public function todasLojasElegiveisConcluidas(CaptacaoLote $lote): bool
    {
        return $this->lojasComPedidoNaoConcluido($lote)->isEmpty();
    }

    /**
     * Lojas com quantidade informada no lote e captação ainda não concluída.
     *
     * @return Collection<int, Cliente>
     */
    public function lojasComPedidoNaoConcluido(CaptacaoLote $lote): Collection
    {
        $lote->loadMissing(['pedidos.itens']);

        return $this->lojasComFrutasVinculadas($lote)
            ->filter(function (Cliente $cliente) use ($lote): bool {
                $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);

                return $pedido !== null
                    && $this->pedidoTemQuantidade($pedido)
                    && ! $pedido->captacao_concluida;
            })
            ->values();
    }

    public function pedidoAnteriorCaptacao(int $idCliente, CaptacaoLote $loteAtual): ?Pedido
    {
        return Pedido::query()
            ->select('pedidos.*')
            ->with(['itens.fruta:id,nome,unidade_medicao', 'lote:id,data_referencia'])
            ->join('captacao_lotes', 'captacao_lotes.id', '=', 'pedidos.id_captacao_lote')
            ->where('pedidos.id_cliente', $idCliente)
            ->where('captacao_lotes.tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->when(
                $loteAtual->id_captacao_carteira !== null,
                fn ($q) => $q->where('captacao_lotes.id_captacao_carteira', $loteAtual->id_captacao_carteira),
                fn ($q) => $q->where('captacao_lotes.id_unidade_negocio_faturamento', $loteAtual->id_unidade_negocio_faturamento),
            )
            ->whereDate('captacao_lotes.data_referencia', '<', $loteAtual->data_referencia)
            ->orderByDesc('captacao_lotes.data_referencia')
            ->orderByDesc('pedidos.id')
            ->first();
    }

    private function pedidoTemQuantidade(Pedido $pedido): bool
    {
        foreach ($pedido->itens as $item) {
            if ((float) $item->quantidade > 0) {
                return true;
            }
        }

        return false;
    }
}
