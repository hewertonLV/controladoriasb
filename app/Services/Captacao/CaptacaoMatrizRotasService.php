<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteRota;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Captacao\Pedido;
use App\Models\Veiculo;
use App\Models\Cliente;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CaptacaoMatrizRotasService
{
    /**
     * @param  Collection<int, Cliente>  $clientes
     * @param  array<int, list<int>>  $frutasPorCliente
     * @param  Collection<int, \App\Models\Captacao\Pedido>  $pedidosPorCliente
     * @param  Collection<int, \App\Models\Fruta>  $frutas
     * @return list<array{
     *     id_cliente: int,
     *     loja_nome: string,
     *     id_captacao_rota: int|null,
     *     captacao_concluida: bool,
     *     numero_pedido: string|null,
     *     saida_fisica_nome: string,
     *     ordem_carregamento: int|null,
     *     itens: list<array{
     *         id_fruta: int,
     *         fruta_nome: string,
     *         unidade_medicao: string,
     *         quantidade: string,
     *         preco_venda: string|null,
     *     }>,
     * }>
     */
    public function gruposPorLoja(
        CaptacaoLote $lote,
        Collection $clientes,
        array $frutasPorCliente,
        Collection $pedidosPorCliente,
        Collection $frutas,
    ): array {
        $frutasPorId = $frutas->keyBy('id');
        $grupos = [];
        $saidaFisicaLoja = app(CaptacaoPedidoPorLojaSaidaFisicaService::class);
        $saidaFisicaRomaneio = app(SaidaEstoqueFisicoCaptacaoService::class);
        $usaSaidaPorLoja = $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento;

        foreach ($clientes as $cliente) {
            $pedido = $pedidosPorCliente->get($cliente->id);
            $itens = [];

            foreach ($frutasPorCliente[$cliente->id] ?? [] as $frutaId) {
                $item = $pedido?->itens->firstWhere('id_fruta', $frutaId);
                $quantidade = (float) ($item?->quantidade ?? 0);

                if ($quantidade <= 0) {
                    continue;
                }

                $fruta = $frutasPorId->get($frutaId);

                $itens[] = [
                    'id_fruta' => (int) $frutaId,
                    'fruta_nome' => (string) ($fruta?->nome ?? '—'),
                    'unidade_medicao' => mb_strtoupper(trim((string) ($fruta?->unidade_medicao ?? 'UM')), 'UTF-8'),
                    'quantidade' => (string) $item->quantidade,
                    'preco_venda' => $item->preco_venda !== null ? (string) $item->preco_venda : null,
                ];
            }

            if ($itens === []) {
                continue;
            }

            $idSaidaFisica = $pedido !== null
                ? ($usaSaidaPorLoja
                    ? $saidaFisicaLoja->idSaidaEfetivaParaExibicao($pedido, $lote, $cliente)
                    : $saidaFisicaRomaneio->idSaidaEfetiva($pedido, $lote, $cliente))
                : $saidaFisicaRomaneio->idGalpaoLote($lote);

            $grupos[] = [
                'id_cliente' => $cliente->id,
                'loja_nome' => $cliente->fantasia ?: $cliente->razao_social,
                'id_captacao_rota' => $pedido?->id_captacao_rota,
                'captacao_concluida' => (bool) $pedido?->captacao_concluida,
                'numero_pedido' => $pedido?->numero_pedido,
                'saida_fisica_nome' => $saidaFisicaLoja->labelCurtoUnidadePorId($lote, $idSaidaFisica),
                'ordem_carregamento' => $pedido?->ordem_carregamento !== null
                    ? (int) $pedido->ordem_carregamento
                    : null,
                'itens' => $itens,
            ];
        }

        return $grupos;
    }

    /**
     * Rotas da carteira com motorista/veículo do lote (ADR-0134).
     *
     * @return Collection<int, CaptacaoRota>
     */
    public function rotasDaCarteira(CaptacaoLote $lote): Collection
    {
        if ($lote->id_captacao_carteira === null) {
            return collect();
        }

        $configs = $this->configPorRotaNoLote($lote);

        return CaptacaoRota::query()
            ->where('id_captacao_carteira', $lote->id_captacao_carteira)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(function (CaptacaoRota $rota) use ($configs): CaptacaoRota {
                $cfg = $configs->get($rota->id);

                $rota->nome_motorista = $cfg?->nome_motorista;
                $rota->id_veiculo = $cfg?->id_veiculo;
                $rota->concluida = (bool) ($cfg?->concluida ?? false);
                $rota->setRelation('veiculo', $cfg?->veiculo);

                return $rota;
            });
    }

    public function rotaEstaConcluidaNoLote(CaptacaoLote $lote, int $rotaId): bool
    {
        return CaptacaoLoteRota::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->where('concluida', true)
            ->exists();
    }

    /**
     * Rotas com ao menos um pedido com quantidade no lote.
     *
     * @return Collection<int, int>
     */
    public function idsRotasComPedidoNoLote(CaptacaoLote $lote): Collection
    {
        return Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->whereNotNull('id_captacao_rota')
            ->whereHas('itens', static fn ($q) => $q->where('quantidade', '>', 0))
            ->pluck('id_captacao_rota')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function todasRotasComPedidoEstaoConcluidasNoLote(CaptacaoLote $lote): bool
    {
        return $this->nomesRotasComPedidoNaoConcluidasNoLote($lote) === [];
    }

    /**
     * @return list<string>
     */
    public function nomesRotasComPedidoNaoConcluidasNoLote(CaptacaoLote $lote): array
    {
        $ids = $this->idsRotasComPedidoNoLote($lote);
        if ($ids->isEmpty()) {
            return [];
        }

        $rotas = CaptacaoRota::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'nome'])
            ->keyBy('id');

        $pendentes = [];
        foreach ($ids as $rotaId) {
            if (! $this->rotaEstaConcluidaNoLote($lote, $rotaId)) {
                $pendentes[] = (string) ($rotas->get($rotaId)?->nome ?? "Rota #{$rotaId}");
            }
        }

        sort($pendentes, SORT_NATURAL | SORT_FLAG_CASE);

        return $pendentes;
    }

    public function assertTodasRotasComPedidoConcluidasNoLote(CaptacaoLote $lote): void
    {
        $pendentes = $this->nomesRotasComPedidoNaoConcluidasNoLote($lote);
        if ($pendentes === []) {
            return;
        }

        throw ValidationException::withMessages([
            'captacao_concluida' => 'Conclua todas as rotas na aba Por rota antes de finalizar a captação desta loja: '
                .implode(', ', $pendentes).'.',
        ]);
    }

    public function assertRotaAbertaNoLote(CaptacaoLote $lote, int $rotaId): void
    {
        if ($this->rotaEstaConcluidaNoLote($lote, $rotaId)) {
            throw ValidationException::withMessages([
                'id_captacao_rota' => 'Esta rota foi concluída. Reabra a rota na aba Por rota para alterar vínculos ou a ordem de carregamento.',
            ]);
        }
    }

    private function assertLotePermiteConcluirReabrirRota(CaptacaoLote $lote): void
    {
        if (! $lote->status->permiteEdicaoVinculoRota()) {
            throw ValidationException::withMessages([
                'status' => 'O vínculo de rota deste lote já foi concluído (vendas finalizadas).',
            ]);
        }
    }

    private function assertRotaDaCarteira(CaptacaoLote $lote, CaptacaoRota $rota): void
    {
        if ($rota->id_captacao_carteira !== $lote->id_captacao_carteira) {
            throw ValidationException::withMessages([
                'id_captacao_rota' => 'A rota não pertence à carteira deste lote.',
            ]);
        }
    }

    /**
     * @return array{pode: bool, pendencias: list<string>}
     */
    public function pendenciasParaConcluirRota(CaptacaoLote $lote, int $rotaId): array
    {
        $pendencias = [];

        $config = CaptacaoLoteRota::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->first();

        if (trim((string) ($config?->nome_motorista ?? '')) === '') {
            $pendencias[] = 'Informe o nome do motorista.';
        }

        if ($config?->id_veiculo === null) {
            $pendencias[] = 'Selecione o veículo da rota.';
        }

        $pedidos = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->with(['itens', 'cliente:id,fantasia,razao_social'])
            ->get();

        $pedidosComQuantidade = $pedidos->filter(fn (Pedido $p) => $this->pedidoTemQuantidadeCaptada($p));

        if ($pedidosComQuantidade->isEmpty()) {
            $pendencias[] = 'Vincule ao menos uma loja com quantidade captada à rota.';
        }

        foreach ($pedidosComQuantidade as $pedido) {
            $nomeLoja = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: 'Loja';

            if (! $pedido->captacao_concluida) {
                $pendencias[] = "Conclua a captação de {$nomeLoja}.";
            }

            if ($pedido->ordem_carregamento === null) {
                $pendencias[] = "Defina a ordem de carregamento de {$nomeLoja}.";
            }
        }

        $quantidadeLojas = $pedidosComQuantidade->count();
        if ($quantidadeLojas > 0) {
            $ordensDefinidas = $pedidosComQuantidade
                ->pluck('ordem_carregamento')
                ->filter(static fn ($ordem) => $ordem !== null)
                ->map(static fn ($ordem) => (int) $ordem)
                ->sort()
                ->values()
                ->all();

            if (count($ordensDefinidas) === $quantidadeLojas
                && $ordensDefinidas !== range(1, $quantidadeLojas)) {
                $pendencias[] = "Complete a sequência de carregamento de 1 a {$quantidadeLojas} em todas as lojas da rota, sem pular números.";
            }
        }

        return [
            'pode' => $pendencias === [],
            'pendencias' => $pendencias,
        ];
    }

    public function concluirRota(CaptacaoLote $lote, CaptacaoRota $rota): CaptacaoLoteRota
    {
        $this->assertLotePermiteConcluirReabrirRota($lote);
        $this->assertRotaDaCarteira($lote, $rota);

        if ($this->rotaEstaConcluidaNoLote($lote, $rota->id)) {
            return $this->configRotaNoLote($lote, $rota);
        }

        $validacao = $this->pendenciasParaConcluirRota($lote, $rota->id);
        if (! $validacao['pode']) {
            throw ValidationException::withMessages([
                'rota' => $validacao['pendencias'],
            ]);
        }

        try {
            return DB::transaction(function () use ($lote, $rota): CaptacaoLoteRota {
                $config = CaptacaoLoteRota::query()->updateOrCreate(
                    [
                        'id_captacao_lote' => $lote->id,
                        'id_captacao_rota' => $rota->id,
                    ],
                    [
                        'concluida' => true,
                    ],
                )->refresh();

                app(EfetivarDemandasMovimentacaoRotaCaptacaoService::class)->executar($lote, $rota);

                return $config;
            });
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'rota' => [$e->getMessage()],
            ]);
        }
    }

    public function reabrirRota(CaptacaoLote $lote, CaptacaoRota $rota): CaptacaoLoteRota
    {
        $this->assertLotePermiteConcluirReabrirRota($lote);
        $this->assertRotaDaCarteira($lote, $rota);

        return DB::transaction(function () use ($lote, $rota): CaptacaoLoteRota {
            app(CaptacaoDemandaRotaService::class)->assertPodeReabrirRota($lote, $rota);
            app(CaptacaoDemandaRotaService::class)->removerDemandasAoReabrir($lote, $rota);

            return CaptacaoLoteRota::query()->updateOrCreate(
                [
                    'id_captacao_lote' => $lote->id,
                    'id_captacao_rota' => $rota->id,
                ],
                [
                    'concluida' => false,
                ],
            )->refresh();
        });
    }

    private function configRotaNoLote(CaptacaoLote $lote, CaptacaoRota $rota): CaptacaoLoteRota
    {
        return CaptacaoLoteRota::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rota->id)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, CaptacaoLoteRota>
     */
    public function configPorRotaNoLote(CaptacaoLote $lote): Collection
    {
        return CaptacaoLoteRota::query()
            ->where('id_captacao_lote', $lote->id)
            ->with('veiculo:id,nome,id_sbs')
            ->get()
            ->keyBy('id_captacao_rota');
    }

    /**
     * @return Collection<int, Veiculo>
     */
    public function veiculosDisponiveis(): Collection
    {
        return Veiculo::query()
            ->ativos()
            ->orderBy('nome')
            ->get(['id', 'id_sbs', 'nome']);
    }

    /**
     * Veículos já vinculados a outras rotas do mesmo lote.
     *
     * @return list<int>
     */
    public function idsVeiculosOcupadosNoLote(CaptacaoLote $lote, ?int $excetoRotaId = null): array
    {
        return CaptacaoLoteRota::query()
            ->where('id_captacao_lote', $lote->id)
            ->whereNotNull('id_veiculo')
            ->when($excetoRotaId !== null, fn ($q) => $q->where('id_captacao_rota', '!=', $excetoRotaId))
            ->pluck('id_veiculo')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Veiculo>
     */
    public function veiculosDisponiveisParaRota(CaptacaoLote $lote, int $rotaId): Collection
    {
        $ocupados = $this->idsVeiculosOcupadosNoLote($lote, $rotaId);

        return $this->veiculosDisponiveis()->filter(
            fn (Veiculo $veiculo): bool => ! in_array($veiculo->id, $ocupados, true),
        )->values();
    }

    public function atualizarVeiculoRota(CaptacaoLote $lote, CaptacaoRota $rota, ?int $veiculoId): CaptacaoLoteRota
    {
        $this->assertRotaAbertaNoLote($lote, $rota->id);

        if (! $lote->status->permiteEdicaoVinculoRota()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_veiculo' => 'O vínculo de rota deste lote já foi concluído (vendas finalizadas).',
            ]);
        }

        if ($rota->id_captacao_carteira !== $lote->id_captacao_carteira) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id_veiculo' => 'A rota não pertence à carteira deste lote.',
            ]);
        }

        if ($veiculoId !== null) {
            $veiculoValido = Veiculo::query()
                ->ativos()
                ->whereKey($veiculoId)
                ->exists();

            if (! $veiculoValido) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_veiculo' => 'Selecione um veículo ativo.',
                ]);
            }

            $jaVinculado = in_array($veiculoId, $this->idsVeiculosOcupadosNoLote($lote, $rota->id), true);

            if ($jaVinculado) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_veiculo' => 'Este veículo já está vinculado a outra rota deste lote.',
                ]);
            }
        }

        $config = CaptacaoLoteRota::query()->updateOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_captacao_rota' => $rota->id,
            ],
            [
                'id_veiculo' => $veiculoId,
            ],
        );

        return $config->refresh()->load('veiculo:id,nome,id_sbs');
    }

    public function atualizarNomeMotorista(CaptacaoLote $lote, CaptacaoRota $rota, ?string $nomeMotorista): CaptacaoLoteRota
    {
        $this->assertRotaAbertaNoLote($lote, $rota->id);

        if (! $lote->status->permiteEdicaoVinculoRota()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nome_motorista' => 'O vínculo de rota deste lote já foi concluído (vendas finalizadas).',
            ]);
        }

        if ($rota->id_captacao_carteira !== $lote->id_captacao_carteira) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nome_motorista' => 'A rota não pertence à carteira deste lote.',
            ]);
        }

        $nome = $nomeMotorista !== null ? trim($nomeMotorista) : null;

        $config = CaptacaoLoteRota::query()->updateOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_captacao_rota' => $rota->id,
            ],
            [
                'nome_motorista' => $nome !== '' ? $nome : null,
            ],
        );

        return $config->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $gruposLoja
     * @return list<array{
     *     id_captacao_rota: int,
     *     rota_nome: string,
     *     motorista_nome: string|null,
     *     id_veiculo: int|null,
     *     veiculo_rotulo: string|null,
     *     concluida: bool,
     *     pode_concluir: bool,
     *     pendencias_conclusao: list<string>,
     *     total_lojas: int,
     *     lojas: list<array<string, mixed>>,
     * }>
     */
    public function gruposOrdemCarregamento(array $gruposLoja, Collection $rotas, ?CaptacaoLote $lote = null): array
    {
        /** @var array<int, array<string, mixed>> $porRota */
        $porRota = [];

        foreach ($gruposLoja as $grupo) {
            if ($grupo['id_captacao_rota'] === null) {
                continue;
            }

            $rotaId = (int) $grupo['id_captacao_rota'];

            if (! isset($porRota[$rotaId])) {
                $rotaModel = $rotas->firstWhere('id', $rotaId);
                $veiculo = $rotaModel?->veiculo;

                $porRota[$rotaId] = [
                    'id_captacao_rota' => $rotaId,
                    'rota_nome' => (string) ($rotaModel?->nome ?? 'Rota'),
                    'motorista_nome' => $rotaModel?->nome_motorista,
                    'id_veiculo' => $rotaModel?->id_veiculo,
                    'veiculo_rotulo' => $veiculo !== null
                        ? "{$veiculo->nome} (SBS {$veiculo->id_sbs})"
                        : null,
                    'concluida' => (bool) ($rotaModel?->concluida ?? false),
                    'lojas' => [],
                ];
            }

            $porRota[$rotaId]['lojas'][] = [
                'id_cliente' => $grupo['id_cliente'],
                'loja_nome' => $grupo['loja_nome'],
                'ordem_carregamento' => $grupo['ordem_carregamento'],
                'captacao_concluida' => $grupo['captacao_concluida'],
                'itens' => $grupo['itens'],
            ];
        }

        $grupos = array_values($porRota);

        usort($grupos, fn (array $a, array $b): int => strcasecmp($a['rota_nome'], $b['rota_nome']));

        foreach ($grupos as &$grupoRota) {
            usort($grupoRota['lojas'], function (array $a, array $b): int {
                $ordemA = $a['ordem_carregamento'] ?? 9999;
                $ordemB = $b['ordem_carregamento'] ?? 9999;
                if ($ordemA !== $ordemB) {
                    return $ordemA <=> $ordemB;
                }

                return strcasecmp($a['loja_nome'], $b['loja_nome']);
            });
            $grupoRota['total_lojas'] = count($grupoRota['lojas']);

            if ($lote !== null && ! ($grupoRota['concluida'] ?? false)) {
                $validacao = $this->pendenciasParaConcluirRota($lote, (int) $grupoRota['id_captacao_rota']);
                $grupoRota['pode_concluir'] = $validacao['pode'];
                $grupoRota['pendencias_conclusao'] = $validacao['pendencias'];
            } else {
                $grupoRota['pode_concluir'] = false;
                $grupoRota['pendencias_conclusao'] = [];
            }

            $grupoRota['demandas'] = [];
        }
        unset($grupoRota);

        return $grupos;
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
}
