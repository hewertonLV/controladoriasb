<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\PedidoOrigem;
use App\Exceptions\Captacao\CaptacaoEdicaoBloqueadaException;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Models\Fruta;
use App\Models\User;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
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
        $this->assertRotaPertenceCarteiraDoLote($dados['id_captacao_rota'] ?? null, $lote);

        return DB::transaction(function () use ($lote, $dados, $origem, $user): Pedido {
            $pedido = $this->resolverPedidoLoteCliente(
                $lote,
                (int) $dados['id_cliente'],
                $origem,
                $user,
            );

            $pedido->forceFill([
                'id_captacao_rota' => $dados['id_captacao_rota'] ?? null,
                'data_entrega' => $dados['data_entrega'] ?? null,
                'origem' => $origem,
            ])->save();

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

        $pedido = DB::transaction(function () use ($lote, $cliente, $origem, $user): Pedido {
            CaptacaoLote::query()->whereKey($lote->id)->lockForUpdate()->first();

            $pedido = $this->buscarPedidoMatriz($lote->id, $cliente->id, lock: true);

            if ($pedido !== null) {
                return $this->reativarPedidoMatriz($pedido, $origem, (int) $lote->id_unidade_negocio_galpao);
            }

            try {
                return Pedido::query()->create([
                    'id_captacao_lote' => $lote->id,
                    'id_cliente' => $cliente->id,
                    'id_unidade_negocio_saida_venda' => null,
                    'origem' => $origem,
                    'created_by_user_id' => $user?->id,
                ]);
            } catch (UniqueConstraintViolationException|QueryException $e) {
                if (! $this->isDuplicatePedidoLoteCliente($e)) {
                    throw $e;
                }

                $pedido = $this->buscarPedidoMatriz($lote->id, $cliente->id, lock: true);

                if ($pedido === null) {
                    throw $e;
                }

                return $this->reativarPedidoMatriz($pedido, $origem, (int) $lote->id_unidade_negocio_galpao);
            }
        });

        $this->historico->registrarPedido($pedido, 'adicionar_matriz', $origem, $user);

        return $pedido;
    }

    public function removerLojaDaMatriz(
        CaptacaoLote $lote,
        int $idCliente,
        PedidoOrigem $origem,
        ?User $user,
    ): void {
        $this->assertLotePermiteCaptacao($lote);

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $idCliente)
            ->first();

        if ($pedido === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Esta loja não está na matriz.',
            ]);
        }

        $pedido->itens()->delete();
        $this->historico->registrarPedido($pedido, 'remover_matriz', $origem, $user);
        $pedido->delete();
    }

    public function upsertCelulaMatriz(
        CaptacaoLote $lote,
        int $idCliente,
        array $dados,
        PedidoOrigem $origem,
        ?User $user,
    ): PedidoItem {
        $permiteQuantidade = $lote->status->permiteEdicaoQuantidadeCaptacao();
        $permitePreco = $lote->status->permiteEdicaoPreco();

        if (! $permiteQuantidade && ! $permitePreco) {
            throw new CaptacaoEdicaoBloqueadaException('O lote não permite mais alterações de captação.');
        }

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

        if ($permiteQuantidade) {
            if ($pedido->captacao_concluida) {
                if ($permitePreco) {
                    $dados = $this->dadosSomentePreco($pedido, $dados);
                } else {
                    throw ValidationException::withMessages([
                        'quantidade' => 'Captação desta loja já está concluída. Reabra para alterar.',
                    ]);
                }
            }
        } else {
            $this->assertLotePermiteEdicaoPreco($lote);
            $dados = $this->dadosSomentePreco($pedido, $dados);
        }

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

        $fruta = Fruta::query()->findOrFail((int) $dados['id_fruta']);
        $custo = $this->resolverCustoReferenciaItemPedido($lote, $pedido, $fruta);

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

    public function definirCaptacaoConcluida(
        CaptacaoLote $lote,
        int $idCliente,
        bool $concluida,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
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

        if ($concluida && ! $this->pedidoTemQuantidadeCaptada($pedido)) {
            throw ValidationException::withMessages([
                'captacao_concluida' => 'Informe ao menos uma quantidade antes de concluir a captação desta loja.',
            ]);
        }

        $pedido->captacao_concluida = $concluida;
        $pedido->save();

        $this->historico->registrarPedido(
            $pedido,
            $concluida ? 'concluir_captacao' : 'reabrir_captacao',
            $origem,
            $user,
        );

        return $pedido->refresh();
    }

    public function atualizarNumeroPedido(
        CaptacaoLote $lote,
        int $idCliente,
        ?string $numeroPedido,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
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

        if ($pedido->captacao_concluida) {
            throw ValidationException::withMessages([
                'numero_pedido' => 'Captação desta loja já está concluída. Reabra para alterar o número do pedido.',
            ]);
        }

        $numero = $numeroPedido !== null ? trim($numeroPedido) : null;
        $pedido->numero_pedido = $numero !== '' ? $numero : null;
        $pedido->save();

        $this->historico->registrarPedido($pedido, 'atualizar_numero_pedido', $origem, $user);

        return $pedido->refresh();
    }

    public function atualizarRotaPedido(
        CaptacaoLote $lote,
        int $idCliente,
        ?int $rotaId,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        $this->assertLotePermiteVinculoRota($lote);
        $this->assertRotaPertenceCarteiraDoLote($rotaId, $lote);

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

        $pedido->id_captacao_rota = $rotaId;
        $pedido->ordem_carregamento = null;
        $pedido->save();

        $this->historico->registrarPedido($pedido, 'atualizar_rota', $origem, $user);

        return $pedido->refresh();
    }

    /**
     * @return list<array{id_cliente: int, ordem_carregamento: int|null}>
     */
    public function atualizarOrdemCarregamento(
        CaptacaoLote $lote,
        int $idCliente,
        ?int $novaOrdem,
        PedidoOrigem $origem,
        ?User $user,
    ): array {
        $this->assertLotePermiteVinculoRota($lote);

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $idCliente)
            ->first();

        if ($pedido === null || $pedido->id_captacao_rota === null) {
            throw ValidationException::withMessages([
                'ordem_carregamento' => 'Vincule a loja a uma rota antes de definir a ordem de carregamento.',
            ]);
        }

        if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
            throw ValidationException::withMessages([
                'ordem_carregamento' => 'Informe ao menos uma quantidade antes de definir a ordem de carregamento.',
            ]);
        }

        $rotaId = (int) $pedido->id_captacao_rota;

        $pedidosRota = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->with('itens')
            ->get()
            ->filter(fn (Pedido $p) => $this->pedidoTemQuantidadeCaptada($p))
            ->values();

        $ordenados = $pedidosRota
            ->sortBy(fn (Pedido $p) => [
                $p->ordem_carregamento ?? 9999,
                $p->id_cliente === $idCliente ? 0 : 1,
                $p->id,
            ])
            ->values();

        $target = $ordenados->firstWhere('id_cliente', $idCliente);
        if ($target === null) {
            throw ValidationException::withMessages([
                'ordem_carregamento' => 'Pedido não encontrado na rota.',
            ]);
        }

        $demais = $ordenados->filter(fn (Pedido $p) => $p->id !== $target->id)->values();

        if ($novaOrdem === null || $novaOrdem < 1) {
            $target->ordem_carregamento = null;
            $target->save();
            $sequencia = $demais;
        } else {
            $novaOrdem = min($novaOrdem, $demais->count() + 1);
            $sequencia = $demais->slice(0, $novaOrdem - 1)
                ->concat([$target])
                ->concat($demais->slice($novaOrdem - 1))
                ->values();
        }

        foreach ($sequencia as $indice => $item) {
            $item->ordem_carregamento = $indice + 1;
            $item->save();
        }

        if ($novaOrdem === null || $novaOrdem < 1) {
            $target->refresh();
            $target->ordem_carregamento = null;
            $target->save();
        }

        $this->historico->registrarPedido($target, 'atualizar_ordem_carregamento', $origem, $user);

        return Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->whereIn('id_cliente', $pedidosRota->pluck('id_cliente'))
            ->orderBy('ordem_carregamento')
            ->orderBy('id_cliente')
            ->get(['id_cliente', 'ordem_carregamento'])
            ->map(fn (Pedido $p) => [
                'id_cliente' => $p->id_cliente,
                'ordem_carregamento' => $p->ordem_carregamento !== null ? (int) $p->ordem_carregamento : null,
            ])
            ->values()
            ->all();
    }

    private function pedidoTemQuantidadeCaptada(Pedido $pedido): bool
    {
        $pedido->loadMissing('itens');

        foreach ($pedido->itens as $item) {
            if ((float) $item->quantidade > 0) {
                return true;
            }
        }

        return false;
    }

    public function lotePossuiPedidoComQuantidadeSemRota(CaptacaoLote $lote): bool
    {
        $lote->loadMissing(['pedidos.itens']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_captacao_rota === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    public function assertPedidosComQuantidadeTemRota(CaptacaoLote $lote): void
    {
        $lote->loadMissing(['pedidos.cliente', 'pedidos.itens', 'unidadeGalpao']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_captacao_rota === null) {
                $nomeLoja = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: "#{$pedido->id_cliente}";

                throw ValidationException::withMessages([
                    'pedidos' => "A loja «{$nomeLoja}» (galpão {$lote->unidadeGalpao?->nome}) tem quantidade na matriz, mas está sem rota. Vincule a rota na aba Rotas.",
                ]);
            }
        }
    }

    public function lotePossuiPedidoComQuantidadeSemOrdemCarregamento(CaptacaoLote $lote): bool
    {
        $lote->loadMissing(['pedidos.itens']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_captacao_rota === null) {
                continue;
            }

            if ($pedido->ordem_carregamento === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    public function assertPedidosComQuantidadeTemOrdemCarregamento(CaptacaoLote $lote): void
    {
        $lote->loadMissing(['pedidos.cliente', 'pedidos.itens', 'unidadeGalpao']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_captacao_rota === null) {
                continue;
            }

            if ($pedido->ordem_carregamento === null) {
                $nomeLoja = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: "#{$pedido->id_cliente}";

                throw ValidationException::withMessages([
                    'pedidos' => "A loja «{$nomeLoja}» (galpão {$lote->unidadeGalpao?->nome}) está sem ordem de carregamento. Defina na aba Por rota.",
                ]);
            }
        }
    }

    private function assertLotePermiteCaptacao(CaptacaoLote $lote): void
    {
        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            throw new CaptacaoEdicaoBloqueadaException('O lote não está em captação.');
        }
    }

    /**
     * Obtém ou cria o pedido do par lote×cliente com lock e retry em violação de unique (autosave concorrente).
     */
    private function resolverPedidoLoteCliente(
        CaptacaoLote $lote,
        int $idCliente,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        CaptacaoLote::query()->whereKey($lote->id)->lockForUpdate()->first();

        $pedido = $this->buscarPedidoMatriz($lote->id, $idCliente, lock: true);

        if ($pedido !== null) {
            if ($pedido->trashed()) {
                return $this->reativarPedidoMatriz($pedido, $origem, (int) $lote->id_unidade_negocio_galpao);
            }

            return $pedido;
        }

        try {
            return Pedido::query()->create([
                'id_captacao_lote' => $lote->id,
                'id_cliente' => $idCliente,
                'origem' => $origem,
                'created_by_user_id' => $user?->id,
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (! $this->isDuplicatePedidoLoteCliente($e)) {
                throw $e;
            }

            $pedido = $this->buscarPedidoMatriz($lote->id, $idCliente, lock: true);

            if ($pedido === null) {
                throw $e;
            }

            if ($pedido->trashed()) {
                return $this->reativarPedidoMatriz($pedido, $origem, (int) $lote->id_unidade_negocio_galpao);
            }

            return $pedido;
        }
    }

    private function buscarPedidoMatriz(int $idLote, int $idCliente, bool $lock = false): ?Pedido
    {
        $query = Pedido::query()
            ->withTrashed()
            ->where('id_captacao_lote', $idLote)
            ->where('id_cliente', $idCliente);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function reativarPedidoMatriz(Pedido $pedido, PedidoOrigem $origem, int $idGalpaoPadrao): Pedido
    {
        if (! $pedido->trashed()) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Esta loja já está na matriz.',
            ]);
        }

        $pedido->restore();
        $pedido->forceFill([
            'origem' => $origem,
            'captacao_concluida' => false,
            'id_captacao_rota' => null,
            'numero_pedido' => null,
            'ordem_carregamento' => null,
            'data_entrega' => null,
            'id_unidade_negocio_saida_venda' => null,
        ])->save();

        return $pedido->fresh() ?? $pedido;
    }

    private function isDuplicatePedidoLoteCliente(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'pedido_lote_cliente_uq')
            || (str_contains($message, 'Duplicate entry') && str_contains($message, '`pedidos`'));
    }

    public function garantirSaidaFisicaVendaPadraoGalpao(CaptacaoLote $lote): void
    {
        $resolver = app(SaidaEstoqueFisicoCaptacaoService::class);
        $lote->loadMissing(['pedidos.cliente', 'pedidos.itens']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_unidade_negocio_saida_venda !== null) {
                continue;
            }

            $cliente = $pedido->cliente;
            if ($cliente === null) {
                $pedido->id_unidade_negocio_saida_venda = $resolver->idGalpaoLote($lote);
            } else {
                $pedido->id_unidade_negocio_saida_venda = $resolver->idSaidaPadraoParaCliente($cliente, $lote);
            }

            $pedido->save();
        }
    }

    public function atualizarSaidaFisicaVendaPedidoPorLoja(
        CaptacaoLote $lote,
        int $idCliente,
        int $idUnidadeSaida,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            throw ValidationException::withMessages([
                'status' => 'A saída física da loja só pode ser alterada com a captação em andamento.',
            ]);
        }

        $resolver = app(\App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService::class);

        if (! $resolver->unidadePermitida($lote, $idUnidadeSaida)) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_saida_venda' => 'A unidade de saída não é permitida para este lote.',
            ]);
        }

        return DB::transaction(function () use ($lote, $idCliente, $idUnidadeSaida, $origem, $user): Pedido {
            $pedido = $this->resolverPedidoLoteCliente($lote, $idCliente, $origem, $user);
            $pedido->id_unidade_negocio_saida_venda = $idUnidadeSaida;
            $pedido->save();

            $this->historico->registrarPedido($pedido, 'atualizar_saida_loja', $origem, $user);
            $this->recalcularCustosReferenciaPorSaidaFisica($lote, $pedido);

            return $pedido->fresh(['cliente', 'unidadeSaidaVenda', 'itens.fruta']);
        });
    }

    /**
     * @return array<int, string|null> id_fruta => custo_referencia
     */
    public function recalcularCustosReferenciaPorSaidaFisica(CaptacaoLote $lote, Pedido $pedido): array
    {
        $pedido->loadMissing(['itens.fruta', 'cliente']);
        $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
        $idSaida = app(CaptacaoPedidoPorLojaSaidaFisicaService::class)->idSaidaEfetivaParaExibicao(
            $pedido,
            $lote,
            $cliente,
        );
        $dataReferencia = $lote->data_referencia !== null
            ? Carbon::parse($lote->data_referencia)->startOfDay()
            : null;

        $custos = [];

        foreach ($pedido->itens as $item) {
            $fruta = $item->fruta;
            if ($fruta === null) {
                continue;
            }

            $custo = $this->precificacao->custoReferenciaPorUmNaSaidaFisica(
                $idSaida,
                (int) $lote->id_unidade_negocio_faturamento,
                $fruta,
                $dataReferencia,
            );

            $item->custo_referencia = $custo;
            $item->save();
            $custos[(int) $item->id_fruta] = $custo;
        }

        return $custos;
    }

    public function atualizarSaidaFisicaVenda(
        CaptacaoLote $lote,
        int $idCliente,
        int $idUnidadeSaida,
        PedidoOrigem $origem,
        ?User $user,
    ): Pedido {
        if ($lote->status !== CaptacaoLoteStatus::SaidaEstoqueFisico) {
            throw ValidationException::withMessages([
                'status' => 'A saída física só pode ser alterada na etapa correspondente.',
            ]);
        }

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $idCliente)
            ->first();

        if ($pedido === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Loja não encontrada neste lote.',
            ]);
        }

        $resolver = app(SaidaEstoqueFisicoCaptacaoService::class);

        if (! $resolver->unidadePermitida($lote, $idUnidadeSaida)) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_saida_venda' => 'A unidade de saída deve ser o galpão ou o HUB de origem do lote.',
            ]);
        }

        $pedido->id_unidade_negocio_saida_venda = $idUnidadeSaida;
        $pedido->save();

        $this->historico->registrarPedido($pedido, 'atualizar_saida_fisica_venda', $origem, $user);

        return $pedido->fresh(['cliente', 'unidadeSaidaVenda']);
    }

    public function assertSaidaFisicaVendaDefinidaParaLote(CaptacaoLote $lote): void
    {
        $lote->loadMissing(['pedidos.itens']);

        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_unidade_negocio_saida_venda === null) {
                $nome = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: "#{$pedido->id_cliente}";

                throw ValidationException::withMessages([
                    'saida_fisica' => "Defina a saída física da loja «{$nome}» antes de concluir.",
                ]);
            }
        }
    }

    public function sincronizarOrigemFisicaItensComSaidaVenda(CaptacaoLote $lote): void
    {
        $lote->loadMissing(['pedidos.itens']);

        foreach ($lote->pedidos as $pedido) {
            if ($pedido->id_unidade_negocio_saida_venda === null) {
                continue;
            }

            $pedido->itens()->update([
                'id_unidade_origem_fisica' => $pedido->id_unidade_negocio_saida_venda,
            ]);
        }
    }

    private function resolverCustoReferenciaItemPedido(CaptacaoLote $lote, Pedido $pedido, Fruta $fruta): ?string
    {
        $pedido->loadMissing('cliente');
        $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
        $idSaida = app(CaptacaoPedidoPorLojaSaidaFisicaService::class)->idSaidaEfetivaParaExibicao(
            $pedido,
            $lote,
            $cliente,
        );
        $dataReferencia = $lote->data_referencia !== null
            ? Carbon::parse($lote->data_referencia)->startOfDay()
            : null;

        return $this->precificacao->custoReferenciaPorUmNaSaidaFisica(
            $idSaida,
            (int) $lote->id_unidade_negocio_faturamento,
            $fruta,
            $dataReferencia,
        );
    }

    private function assertLotePermiteEdicaoPreco(CaptacaoLote $lote): void
    {
        if (! $lote->status->permiteEdicaoPreco()) {
            throw new CaptacaoEdicaoBloqueadaException('O preço não pode mais ser alterado neste lote.');
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function dadosSomentePreco(Pedido $pedido, array $dados): array
    {
        $existente = PedidoItem::query()
            ->where('id_pedido', $pedido->id)
            ->where('id_fruta', $dados['id_fruta'])
            ->first();

        if ($existente === null) {
            throw ValidationException::withMessages([
                'preco_venda' => 'Informe a quantidade na captação antes de alterar o preço.',
            ]);
        }

        $quantidadeEnviada = isset($dados['incremento'])
            ? null
            : number_format((float) ($dados['quantidade'] ?? 0), 3, '.', '');

        if (isset($dados['incremento']) || ($quantidadeEnviada !== null && $quantidadeEnviada !== (string) $existente->quantidade)) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade está travada após a captação. Apenas o preço pode ser alterado.',
            ]);
        }

        $dados['quantidade'] = $existente->quantidade;
        unset($dados['incremento']);

        return $dados;
    }

    private function assertLotePermiteVinculoRota(CaptacaoLote $lote): void
    {
        if (! $lote->status->permiteEdicaoVinculoRota()) {
            throw new CaptacaoEdicaoBloqueadaException('O vínculo de rota deste lote já foi concluído (vendas finalizadas).');
        }
    }

    private function assertRotaPertenceCarteiraDoLote(?int $rotaId, CaptacaoLote $lote): void
    {
        if ($rotaId === null) {
            return;
        }

        $valida = CaptacaoRota::query()
            ->whereKey($rotaId)
            ->where('id_captacao_carteira', $lote->id_captacao_carteira)
            ->exists();

        if (! $valida) {
            throw ValidationException::withMessages([
                'id_captacao_rota' => 'A rota selecionada não pertence à carteira deste lote.',
            ]);
        }
    }
}
