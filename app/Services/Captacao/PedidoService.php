<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\PedidoOrigem;
use App\Exceptions\Captacao\CaptacaoEdicaoBloqueadaException;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PedidoService
{
    public function __construct(
        private readonly CaptacaoPrecificacaoService $precificacao,
        private readonly CaptacaoHistoricoService $historico,
        private readonly ClienteFrutaVinculoService $frutaVinculos,
    ) {}

    /**
     * @param  array{
     *     id_cliente: int,
     *     id_captacao_rota?: int|null,
     *     data_entrega?: string|null,
     *     itens: list<array{
     *         id_fruta: int,
     *         quantidade: numeric-string|float|int,
     *         preco_venda?: numeric-string|float|null,
     *         id_unidade_origem_fisica?: int|null,
     *     }>
     * }  $dados
     */
    public function salvarPedidoComItens(
        CaptacaoLote $lote,
        array $dados,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        $this->assertLotePermiteCaptacao($lote);

        return DB::transaction(function () use ($lote, $dados, $origem, $user): Pedido {
            $pedido = Pedido::query()->updateOrCreate(
                [
                    'id_captacao_lote' => $lote->id,
                    'id_cliente' => $dados['id_cliente'],
                ],
                [
                    'id_captacao_rota' => $dados['id_captacao_rota'] ?? null,
                    'data_entrega' => $dados['data_entrega'] ?? null,
                    'origem' => $origem,
                    'created_by_user_id' => $user?->id,
                ],
            );

            $this->historico->registrarPedido($pedido, 'salvar', $origem, $user);

            foreach ($dados['itens'] as $itemDados) {
                $this->upsertItem($lote, $pedido, $itemDados, $origem, $user);
            }

            return $pedido->load(['itens.fruta', 'cliente', 'rota']);
        });
    }

    /**
     * @param  array{
     *     id_fruta: int,
     *     quantidade: numeric-string|float|int,
     *     preco_venda?: numeric-string|float|null,
     *     id_unidade_origem_fisica?: int|null,
     *     version?: int,
     * }  $dados
     */
    public function adicionarLojaNaMatriz(
        CaptacaoLote $lote,
        Cliente $cliente,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        $this->assertLotePermiteCaptacao($lote);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $cliente->id,
            'origem' => $origem,
            'created_by_user_id' => $user?->id,
        ]);

        $this->historico->registrarPedido($pedido, 'adicionar_matriz', $origem, $user);

        return $pedido;
    }

    public function upsertCelulaMatriz(
        CaptacaoLote $lote,
        int $idCliente,
        array $dados,
        PedidoOrigem $origem,
        ?User $user,
    ): PedidoItem {
        $this->assertLotePermiteCaptacao($lote);

        $pedido = Pedido::query()->firstOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_cliente' => $idCliente,
            ],
            [
                'origem' => $origem,
                'created_by_user_id' => $user?->id,
            ],
        );

        return $this->upsertItem($lote, $pedido, $dados, $origem, $user);
    }

    /**
     * @param  array{
     *     id_fruta: int,
     *     quantidade: numeric-string|float|int,
     *     preco_venda?: numeric-string|float|null,
     *     id_unidade_origem_fisica?: int|null,
     *     version?: int,
     * }  $dados
     */
    private function upsertItem(
        CaptacaoLote $lote,
        Pedido $pedido,
        array $dados,
        PedidoOrigem $origem,
        ?User $user,
    ): PedidoItem {
        $existente = PedidoItem::query()
            ->where('id_pedido', $pedido->id)
            ->where('id_fruta', $dados['id_fruta'])
            ->first();

        if ($existente !== null && isset($dados['version']) && (int) $dados['version'] !== $existente->version) {
            throw ValidationException::withMessages([
                'version' => 'Conflito de versão. Atualize a célula e tente novamente.',
            ]);
        }

        $this->frutaVinculos->assertFrutaVinculadaAoCliente($pedido->id_cliente, (int) $dados['id_fruta']);

        $custo = $this->precificacao->custoReferenciaPorKg(
            $lote->id_unidade_negocio_galpao,
            $dados['id_fruta'],
        );

        $quantidade = $this->resolverQuantidade($existente, $dados);

        if ($existente === null) {
            $item = PedidoItem::query()->create([
                'id_pedido' => $pedido->id,
                'id_fruta' => $dados['id_fruta'],
                'quantidade' => $quantidade,
                'preco_venda' => $dados['preco_venda'] ?? null,
                'custo_referencia' => $custo,
                'id_unidade_origem_fisica' => $dados['id_unidade_origem_fisica'] ?? null,
                'version' => 1,
            ]);
            $this->historico->registrarItem($item, 'criar', $origem, $user, $dados);

            return $item;
        }

        $existente->fill([
            'quantidade' => $quantidade,
            'preco_venda' => $dados['preco_venda'] ?? $existente->preco_venda,
            'custo_referencia' => $custo ?? $existente->custo_referencia,
            'id_unidade_origem_fisica' => $dados['id_unidade_origem_fisica'] ?? $existente->id_unidade_origem_fisica,
            'version' => $existente->version + 1,
        ]);
        $existente->save();

        $this->historico->registrarItem($existente, 'atualizar', $origem, $user, $dados);

        return $existente->refresh();
    }

    /**
     * @param  array{quantidade?: mixed, incremento?: mixed}  $dados
     */
    private function resolverQuantidade(?PedidoItem $existente, array $dados): string
    {
        if (isset($dados['incremento']) && $dados['incremento'] !== null && $dados['incremento'] !== '') {
            $base = $existente !== null ? (float) $existente->quantidade : 0.0;

            return number_format(max(0, $base + (float) $dados['incremento']), 3, '.', '');
        }

        return number_format((float) ($dados['quantidade'] ?? 0), 3, '.', '');
    }

    private function assertLotePermiteCaptacao(CaptacaoLote $lote): void
    {
        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            throw new CaptacaoEdicaoBloqueadaException('O lote não está em captação.');
        }
    }
}
