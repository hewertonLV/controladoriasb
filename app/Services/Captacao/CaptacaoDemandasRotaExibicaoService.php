<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoDemandaStatus;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\Pedido;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;

use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Support\Collection;

final class CaptacaoDemandasRotaExibicaoService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function cardsTransferenciaModulo(?User $user = null): array
    {
        $cards = $this->cardsCaptacaoPorTipo(CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA, $user);

        return array_merge($cards, $this->cardsTransferenciaManualModulo());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cardsVendaModulo(?User $user = null): array
    {
        return $this->cardsCaptacaoPorTipo(CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA, $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsCaptacaoPorTipo(string $tipo, ?User $user): array
    {
        $query = CaptacaoLoteMovimentacao::query()
            ->with([
                'linhas.fruta:id,nome,unidade_medicao',
                'linhas.pedido.cliente:id,fantasia,razao_social',
                'fruta:id,nome,unidade_medicao',
                'lote:id,data_referencia,id_unidade_negocio_galpao,id_captacao_carteira',
                'lote.carteira:id,nome',
                'captacaoRota:id,nome',
                'pedido.cliente:id,fantasia,razao_social',
            ])
            ->where('tipo', $tipo)
            ->where('status_demanda', '!=', CaptacaoDemandaStatus::Concluido->value)
            ->whereNotNull('id_captacao_rota')
            ->orderByDesc('id');

        if ($tipo === CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) {
            $query->whereNull('id_pedido');
        }

        $galpaoIds = app(UnidadeNegocioAccessService::class)->unidadeIdsPermitidas($user ?? auth()->user());
        if ($galpaoIds !== null) {
            $query->whereHas('lote', fn ($q) => $q->whereIn('id_unidade_negocio_galpao', $galpaoIds));
        }

        $vinculos = $query->get();
        if ($vinculos->isEmpty()) {
            return [];
        }

        $unidadesPorId = $this->unidadesPorId($vinculos);
        $cards = [];

        foreach ($vinculos as $vinculo) {
            $lote = $vinculo->lote ?? CaptacaoLote::query()->findOrFail($vinculo->id_captacao_lote);

            if ($tipo === CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
                $cards[] = $this->montarCardTransferencia($lote, $vinculo, $unidadesPorId);
            } else {
                $pedido = $vinculo->pedido;
                $cards[] = $this->montarCardVenda($lote, $vinculo, $pedido);
            }
        }

        return $cards;
    }

    public function cardDemandaCaptacao(CaptacaoLoteMovimentacao $demanda): ?array
    {
        $demanda->load([
            'linhas.fruta:id,nome,unidade_medicao',
            'linhas.pedido.cliente:id,fantasia,razao_social',
            'fruta:id,nome,unidade_medicao',
            'lote.carteira:id,nome',
            'captacaoRota:id,nome',
            'pedido.cliente:id,fantasia,razao_social',
        ]);

        $lote = $demanda->lote;
        if ($lote === null) {
            return null;
        }

        $unidadesPorId = $this->unidadesPorId(collect([$demanda]));

        if ($demanda->tipo === CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
            return $this->montarCardTransferencia($lote, $demanda, $unidadesPorId);
        }

        if ($demanda->tipo === CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) {
            return $this->montarCardVenda($lote, $demanda, $demanda->pedido);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsTransferenciaManualModulo(): array
    {
        $demandas = \App\Models\Movimentacoes\TransferenciaDemanda::query()
            ->with(['unidadeOrigem', 'unidadeDestino', 'linhas.fruta'])
            ->where('status', '!=', \App\Enums\TransferenciaDemandaStatus::Concluido->value)
            ->orderByDesc('id')
            ->get();

        $cards = [];
        foreach ($demandas as $demanda) {
            $status = \App\Enums\TransferenciaDemandaStatus::tryFrom($demanda->status)
                ?? \App\Enums\TransferenciaDemandaStatus::DemandaCriada;

            $frutasResumo = $demanda->linhas
                ->map(fn ($l) => ($l->fruta?->nome ?? 'Fruta').' · '.rtrim(rtrim(number_format((float) $l->qtd_um, 3, '.', ''), '0'), '.'))
                ->take(2)
                ->implode(' · ');

            $cards[] = [
                'id' => 'manual-'.$demanda->id,
                'tipo' => 'TRANSFERENCIA_MANUAL',
                'tipo_label' => 'Demanda manual',
                'titulo' => ($demanda->unidadeOrigem?->nome ?? 'Origem').' → '.($demanda->unidadeDestino?->nome ?? 'Destino'),
                'subtitulo' => $frutasResumo !== '' ? $frutasResumo : 'Sem linhas',
                'status_demanda' => $status->value,
                'status_label' => $status->label(),
                'estado_classe' => match ($status) {
                    \App\Enums\TransferenciaDemandaStatus::DemandaCriada => 'nao_iniciado',
                    \App\Enums\TransferenciaDemandaStatus::Concluido => 'concluido',
                    default => 'em_andamento',
                },
                'contexto' => 'Demanda manual #'.$demanda->id,
                'url_show' => route('admin.movimentacoes.transferencias.demandas.edit', $demanda),
                'detalhes' => [],
                'aviso_cigam' => false,
                'acoes' => [],
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{
     *     id: int,
     *     tipo: string,
     *     tipo_label: string,
     *     titulo: string,
     *     subtitulo: string|null,
     *     status_demanda: string,
     *     status_label: string,
     *     estado_classe: string,
     *     icone: string,
     *     cor_bootstrap: string,
     *     detalhes: list<string>,
     *     aviso_cigam: bool,
     *     url: string|null,
     *     acoes: array<string, mixed>,
     * }>
     */
    public function cardsPorRota(CaptacaoLote $lote, int $rotaId): array
    {
        $vinculos = CaptacaoLoteMovimentacao::query()
            ->with(['linhas.fruta', 'linhas.pedido.cliente', 'fruta:id,nome,unidade_medicao', 'captacaoRota:id,nome'])
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->orderBy('tipo')
            ->orderBy('id')
            ->get();

        if ($vinculos->isEmpty()) {
            return [];
        }

        $pedidosPorId = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->with('cliente:id,fantasia,razao_social')
            ->get()
            ->keyBy('id');

        $unidadesPorId = $this->unidadesPorId($vinculos);

        $cards = [];

        foreach ($vinculos as $vinculo) {
            if ($vinculo->tipo === CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
                $cards[] = $this->montarCardTransferencia($lote, $vinculo, $unidadesPorId);
            } elseif ($vinculo->tipo === CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) {
                if ($vinculo->id_pedido !== null) {
                    $pedido = $pedidosPorId->get($vinculo->id_pedido);
                    $cards[] = $this->montarCardVenda($lote, $vinculo, $pedido);
                } else {
                    $cards[] = $this->montarCardVenda($lote, $vinculo, null);
                }
            }
        }

        return $cards;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CaptacaoLoteMovimentacao>  $vinculos
     * @return array<int, UnidadeNegocio>
     */
    private function unidadesPorId($vinculos): array
    {
        $ids = $vinculos
            ->pluck('id_unidade_negocio_origem')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return UnidadeNegocio::query()
            ->whereIn('id', $ids)
            ->get(['id', 'nome'])
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  array<int, UnidadeNegocio>  $unidadesPorId
     * @return array<string, mixed>
     */
    private function montarCardTransferencia(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo, array $unidadesPorId): array
    {
        $status = CaptacaoDemandaStatus::tryFrom((string) $vinculo->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        $vinculo->loadMissing('linhas.fruta');

        $unidadeOrigem = $unidadesPorId[(int) $vinculo->id_unidade_negocio_origem] ?? null;
        $romaneio = $this->montarRomaneioTransferencia($lote, $vinculo);
        $detalhes = [];

        if ($romaneio === []) {
            if ($unidadeOrigem !== null) {
                $detalhes[] = 'Origem física: '.$unidadeOrigem->nome;
            }

            foreach ($vinculo->linhas as $linha) {
                $frutaNome = (string) ($linha->fruta?->nome ?? 'Fruta');
                $qtdDemanda = (float) $linha->qtd_um;
                if ($qtdDemanda <= 0) {
                    continue;
                }

                $detalhes[] = $frutaNome.': '.rtrim(rtrim(number_format($qtdDemanda, 3, '.', ''), '0'), '.')
                    .' '.mb_strtoupper((string) ($linha->fruta?->unidade_medicao ?? 'UM'), 'UTF-8');
            }

            if ($detalhes === [] && $vinculo->id_fruta !== null) {
                $frutaNome = (string) ($vinculo->fruta?->nome ?? 'Fruta');
                $qtdDemanda = (float) ($vinculo->qtd_um ?? 0);
                if ($qtdDemanda > 0) {
                    $detalhes[] = $frutaNome.': '.rtrim(rtrim(number_format($qtdDemanda, 3, '.', ''), '0'), '.')
                        .' '.mb_strtoupper((string) ($vinculo->fruta?->unidade_medicao ?? 'UM'), 'UTF-8');
                }
            }
        }

        $movimentacao = $this->movimentacaoSaidaTransferencia($vinculo);
        if ($movimentacao !== null) {
            if ($movimentacao->numero_nf_origem) {
                $detalhes[] = 'Referência: '.$movimentacao->numero_nf_origem;
            }
            $detalhes[] = 'Transferência SB: '.$this->rotuloStatusTransferenciaSb($movimentacao);
        }

        $transferenciaOrigemId = (int) ($vinculo->transferencia_origem_id ?? 0);
        $primeiraFruta = $vinculo->linhas->first()?->fruta?->nome
            ?? $vinculo->fruta?->nome
            ?? 'Transferência';
        $totalFrutas = $vinculo->linhas->count() ?: ($vinculo->id_fruta !== null ? 1 : 0);
        $titulo = $totalFrutas > 1
            ? "Transferência · {$totalFrutas} frutas"
            : $primeiraFruta;

        return [
            'id' => $vinculo->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
            'tipo_label' => 'Transferência',
            'titulo' => $titulo,
            'subtitulo' => $this->contextoCaptacao($lote, $vinculo, $unidadeOrigem?->nome),
            'status_demanda' => $status->value,
            'status_label' => $status->label(),
            'estado_classe' => $this->estadoClasseCard($status),
            'icone' => 'ri-truck-line',
            'cor_bootstrap' => 'primary',
            'detalhes' => $detalhes,
            'romaneio' => $romaneio,
            'romaneio_resumo' => $this->montarResumoRomaneioTransferencia($lote, $vinculo, $unidadeOrigem),
            'aviso_cigam' => true,
            'demanda_automatica_rota' => $vinculo->id_captacao_rota !== null,
            'contexto' => $this->contextoCaptacao($lote, $vinculo),
            'url' => $transferenciaOrigemId > 0
                ? route('admin.movimentacoes.transferencias.show', $transferenciaOrigemId)
                : null,
            'url_show' => route('admin.movimentacoes.transferencias.demandas-captacao.show', $vinculo),
            'acoes' => $this->acoesTransferencia($lote, $vinculo, $status),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarCardVenda(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo, ?Pedido $pedido): array
    {
        $status = CaptacaoDemandaStatus::tryFrom((string) $vinculo->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        $vinculo->loadMissing(['linhas.fruta', 'linhas.pedido.cliente', 'captacaoRota']);

        if ($vinculo->id_pedido !== null && $pedido !== null) {
            return $this->montarCardVendaLegado($lote, $vinculo, $pedido, $status);
        }

        $romaneio = $this->montarRomaneioVenda($vinculo);
        $rotaNome = $vinculo->captacaoRota?->nome ?? 'Rota';
        $qtdLojas = count($romaneio);

        $titulo = $qtdLojas === 1
            ? ($romaneio[0]['loja_nome'] ?? 'Venda')
            : "Venda · {$rotaNome}";

        $detalhes = [];
        foreach ($romaneio as $bloco) {
            $itensResumo = collect($bloco['itens'] ?? [])
                ->map(fn (array $item): string => $item['fruta_nome'].': '.$item['qtd_formatada'])
                ->implode(' · ');
            if ($itensResumo !== '') {
                $detalhes[] = ($bloco['loja_nome'] ?? 'Loja').' — '.$itensResumo;
            }
        }

        return [
            'id' => $vinculo->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
            'tipo_label' => 'Venda',
            'titulo' => $titulo,
            'subtitulo' => $this->contextoCaptacao($lote, $vinculo),
            'status_demanda' => $status->value,
            'status_label' => $status->label(),
            'estado_classe' => $this->estadoClasseCard($status),
            'icone' => 'ri-shopping-cart-2-line',
            'cor_bootstrap' => 'success',
            'detalhes' => $detalhes,
            'romaneio' => $romaneio,
            'aviso_cigam' => false,
            'contexto' => $this->contextoCaptacao($lote, $vinculo),
            'url' => null,
            'url_show' => route('admin.movimentacoes.vendas.demandas-captacao.show', $vinculo),
            'acoes' => $this->acoesVenda($lote, $vinculo, $status),
        ];
    }

    /**
     * @return list<array{loja_nome: string, ordem: int|null, itens: list<array{fruta_nome: string, qtd_formatada: string, preco_venda: string|null}>}>
     */
    private function montarRomaneioVenda(CaptacaoLoteMovimentacao $vinculo): array
    {
        $linhasPorPedido = $vinculo->linhas
            ->whereNotNull('id_pedido')
            ->groupBy('id_pedido');

        $pedidos = Pedido::query()
            ->with('cliente:id,fantasia,razao_social')
            ->whereIn('id', $linhasPorPedido->keys()->map(fn ($id) => (int) $id)->all())
            ->orderBy('ordem_carregamento')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $romaneio = [];

        foreach ($pedidos as $pedido) {
            $linhas = $linhasPorPedido->get($pedido->id, collect());
            $itens = [];

            foreach ($linhas as $linha) {
                $qtdDemanda = (float) $linha->qtd_um;
                if ($qtdDemanda <= 0) {
                    continue;
                }

                $itens[] = [
                    'fruta_nome' => (string) ($linha->fruta?->nome ?? 'Fruta'),
                    'qtd_formatada' => rtrim(rtrim(number_format($qtdDemanda, 3, '.', ''), '0'), '.')
                        .' '.mb_strtoupper((string) ($linha->fruta?->unidade_medicao ?? 'UM'), 'UTF-8'),
                    'preco_venda' => $linha->preco_venda !== null
                        ? number_format((float) $linha->preco_venda, 2, ',', '.')
                        : null,
                ];
            }

            if ($itens === []) {
                continue;
            }

            $romaneio[] = [
                'loja_nome' => $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: 'Loja',
                'ordem' => $pedido->ordem_carregamento,
                'itens' => $itens,
            ];
        }

        return $romaneio;
    }

    /**
     * @return array<string, mixed>
     */
    private function montarCardVendaLegado(
        CaptacaoLote $lote,
        CaptacaoLoteMovimentacao $vinculo,
        Pedido $pedido,
        CaptacaoDemandaStatus $status,
    ): array {
        $nomeLoja = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: 'Loja';

        $detalhes = [];
        if ($pedido->numero_pedido) {
            $detalhes[] = 'Pedido: '.$pedido->numero_pedido;
        }

        $vendaNotaId = (int) ($vinculo->venda_nota_id ?? 0);

        return [
            'id' => $vinculo->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
            'tipo_label' => 'Venda',
            'titulo' => $nomeLoja,
            'subtitulo' => $this->contextoCaptacao($lote, $vinculo),
            'status_demanda' => $status->value,
            'status_label' => $status->label(),
            'estado_classe' => $this->estadoClasseCard($status),
            'icone' => 'ri-shopping-cart-2-line',
            'cor_bootstrap' => 'success',
            'detalhes' => $detalhes,
            'romaneio' => [],
            'aviso_cigam' => false,
            'contexto' => $this->contextoCaptacao($lote, $vinculo),
            'url' => $vendaNotaId > 0 && $status === CaptacaoDemandaStatus::Concluido
                ? route('admin.movimentacoes.vendas.show', $vendaNotaId)
                : null,
            'url_show' => route('admin.movimentacoes.vendas.demandas-captacao.show', $vinculo),
            'acoes' => $this->acoesVenda($lote, $vinculo, $status),
        ];
    }

    private function contextoCaptacao(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo, ?string $extra = null): string
    {
        $partes = array_filter([
            $lote->carteira?->nome,
            $lote->data_referencia?->format('d/m/Y'),
            $vinculo->captacaoRota?->nome ? 'Rota '.$vinculo->captacaoRota->nome : null,
            $extra,
        ]);

        return implode(' · ', $partes);
    }

    /**
     * @return array<string, mixed>
     */
    private function acoesTransferencia(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo, CaptacaoDemandaStatus $status): array
    {
        $demandaAutomaticaRota = $vinculo->id_captacao_rota !== null;

        return [
            'url_iniciar' => route('admin.captacao.lotes.demandas.transferencia.iniciar', [$lote, $vinculo]),
            'url_cigam' => route('admin.movimentacoes.transferencias.demandas-captacao.cigam', $vinculo),
            'url_nf' => route('admin.captacao.lotes.demandas.transferencia.nf', [$lote, $vinculo]),
            'url_excluir' => route('admin.captacao.lotes.demandas.transferencia.excluir', [$lote, $vinculo]),
            'pode_iniciar' => $status === CaptacaoDemandaStatus::Aberto,
            'pode_cigam' => $status === CaptacaoDemandaStatus::Iniciado,
            'pode_nf' => $status === CaptacaoDemandaStatus::Iniciado,
            'pode_excluir' => ! $demandaAutomaticaRota && $status !== CaptacaoDemandaStatus::Concluido,
        ];
    }

    /**
     * @return array{
     *     origem_transferencia: string|null,
     *     destino_faturamento: string|null,
     *     totais_por_fruta: list<array{fruta_nome: string, qtd_formatada: string}>,
     * }
     */
    private function montarResumoRomaneioTransferencia(
        CaptacaoLote $lote,
        CaptacaoLoteMovimentacao $vinculo,
        ?UnidadeNegocio $unidadeOrigem,
    ): array {
        $lote->loadMissing('unidadeFaturamento:id,nome');
        $vinculo->loadMissing('linhas.fruta');

        $totaisPorFruta = [];

        foreach ($vinculo->linhas as $linha) {
            $qtd = (float) $linha->qtd_um;
            if ($qtd <= 0) {
                continue;
            }

            $idFruta = (int) $linha->id_fruta;
            $um = mb_strtoupper((string) ($linha->fruta?->unidade_medicao ?? 'UM'), 'UTF-8');
            $totaisPorFruta[$idFruta]['fruta_nome'] = (string) ($linha->fruta?->nome ?? 'Fruta');
            $totaisPorFruta[$idFruta]['qtd_um'] = ($totaisPorFruta[$idFruta]['qtd_um'] ?? 0) + $qtd;
            $totaisPorFruta[$idFruta]['um'] = $um;
        }

        $totaisFormatados = [];
        foreach ($totaisPorFruta as $total) {
            $totaisFormatados[] = [
                'fruta_nome' => $total['fruta_nome'],
                'qtd_formatada' => $this->formatarQuantidadeUm((float) $total['qtd_um'], $total['um']),
            ];
        }

        return [
            'origem_transferencia' => $unidadeOrigem?->nome,
            'destino_faturamento' => $lote->unidadeFaturamento?->nome,
            'totais_por_fruta' => $totaisFormatados,
        ];
    }

    /**
     * Romaneio por loja da rota: item, quantidade, preço e origem fiscal (saída física da venda).
     *
     * @return list<array{
     *     loja_nome: string,
     *     ordem: int|null,
     *     itens: list<array{
     *         fruta_nome: string,
     *         qtd_formatada: string,
     *         preco_venda: string|null,
     *         origem_fiscal_nome: string,
     *     }>,
     * }>
     */
    private function montarRomaneioTransferencia(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo): array
    {
        $lote->loadMissing([
            'pedidos.itens.fruta',
            'pedidos.cliente:id,fantasia,razao_social,id_unidade_negocio_saida_fisico_padrao',
        ]);
        $vinculo->loadMissing(['linhas.fruta', 'linhas.pedido.cliente']);

        $saidaFisica = app(SaidaEstoqueFisicoCaptacaoService::class);
        $labelsSaida = app(CaptacaoPedidoPorLojaSaidaFisicaService::class);

        $idOrigemDemanda = (int) $vinculo->id_unidade_negocio_origem;
        $rotaId = (int) $vinculo->id_captacao_rota;

        /** @var Collection<int, Pedido> $pedidos */
        $pedidos = $lote->pedidos
            ->filter(fn (Pedido $pedido): bool => (int) $pedido->id_captacao_rota === $rotaId)
            ->filter(fn (Pedido $pedido): bool => $saidaFisica->pedidoExigeTransferenciaParaGalpao($pedido, $lote))
            ->filter(fn (Pedido $pedido): bool => $saidaFisica->idSaidaEfetiva($pedido, $lote) === $idOrigemDemanda)
            ->sortBy([
                ['ordem_carregamento', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($pedidos->isNotEmpty()) {
            return $this->montarRomaneioTransferenciaPorPedidos($pedidos, $lote, $labelsSaida);
        }

        $linhasPorPedido = $vinculo->linhas->whereNotNull('id_pedido')->groupBy('id_pedido');
        if ($linhasPorPedido->isNotEmpty()) {
            return $this->montarRomaneioTransferenciaPorLinhasPedido($vinculo, $lote, $labelsSaida, $linhasPorPedido);
        }

        return $this->montarRomaneioTransferenciaAgregado($vinculo, $lote, $labelsSaida, $idOrigemDemanda);
    }

    /**
     * @param  Collection<int, Pedido>  $pedidos
     * @return list<array{loja_nome: string, ordem: int|null, itens: list<array<string, mixed>>}>
     */
    private function montarRomaneioTransferenciaPorPedidos(
        Collection $pedidos,
        CaptacaoLote $lote,
        CaptacaoPedidoPorLojaSaidaFisicaService $labelsSaida,
    ): array {
        $romaneio = [];

        foreach ($pedidos as $pedido) {
            $idSaidaFiscal = app(SaidaEstoqueFisicoCaptacaoService::class)->idSaidaEfetiva($pedido, $lote);
            $origemFiscal = $labelsSaida->labelUnidadePorId($lote, $idSaidaFiscal) ?? '—';
            $itens = [];

            foreach ($pedido->itens as $item) {
                $qtd = (float) $item->quantidade;
                if ($qtd <= 0) {
                    continue;
                }

                $itens[] = [
                    'fruta_nome' => (string) ($item->fruta?->nome ?? 'Fruta'),
                    'qtd_formatada' => $this->formatarQuantidadeUm(
                        $qtd,
                        mb_strtoupper((string) ($item->fruta?->unidade_medicao ?? 'UM'), 'UTF-8'),
                    ),
                    'preco_venda' => $item->preco_venda !== null && (float) $item->preco_venda > 0
                        ? number_format((float) $item->preco_venda, 2, ',', '.')
                        : null,
                    'origem_fiscal_nome' => $origemFiscal,
                ];
            }

            if ($itens === []) {
                continue;
            }

            $romaneio[] = [
                'loja_nome' => $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: 'Loja',
                'ordem' => $pedido->ordem_carregamento,
                'itens' => $itens,
            ];
        }

        return $romaneio;
    }

    /**
     * @param  Collection<int|string, \Illuminate\Support\Collection<int, \App\Models\Captacao\CaptacaoLoteMovimentacaoLinha>>  $linhasPorPedido
     * @return list<array{loja_nome: string, ordem: int|null, itens: list<array<string, mixed>>}>
     */
    private function montarRomaneioTransferenciaPorLinhasPedido(
        CaptacaoLoteMovimentacao $vinculo,
        CaptacaoLote $lote,
        CaptacaoPedidoPorLojaSaidaFisicaService $labelsSaida,
        Collection $linhasPorPedido,
    ): array {
        $pedidos = Pedido::query()
            ->with('cliente:id,fantasia,razao_social,id_unidade_negocio_saida_fisico_padrao')
            ->whereIn('id', $linhasPorPedido->keys()->map(fn ($id) => (int) $id)->all())
            ->orderBy('ordem_carregamento')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $romaneio = [];
        $saidaFisica = app(SaidaEstoqueFisicoCaptacaoService::class);

        foreach ($pedidos as $pedido) {
            $linhas = $linhasPorPedido->get($pedido->id, collect());
            $origemFiscal = $labelsSaida->labelUnidadePorId(
                $lote,
                $saidaFisica->idSaidaEfetiva($pedido, $lote),
            ) ?? '—';
            $itens = [];

            foreach ($linhas as $linha) {
                $qtd = (float) $linha->qtd_um;
                if ($qtd <= 0) {
                    continue;
                }

                $itens[] = [
                    'fruta_nome' => (string) ($linha->fruta?->nome ?? 'Fruta'),
                    'qtd_formatada' => $this->formatarQuantidadeUm(
                        $qtd,
                        mb_strtoupper((string) ($linha->fruta?->unidade_medicao ?? 'UM'), 'UTF-8'),
                    ),
                    'preco_venda' => $linha->preco_venda !== null
                        ? number_format((float) $linha->preco_venda, 2, ',', '.')
                        : null,
                    'origem_fiscal_nome' => $origemFiscal,
                ];
            }

            if ($itens === []) {
                continue;
            }

            $romaneio[] = [
                'loja_nome' => $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: 'Loja',
                'ordem' => $pedido->ordem_carregamento,
                'itens' => $itens,
            ];
        }

        return $romaneio;
    }

    /**
     * @return list<array{loja_nome: string, ordem: int|null, itens: list<array<string, mixed>>}>
     */
    private function montarRomaneioTransferenciaAgregado(
        CaptacaoLoteMovimentacao $vinculo,
        CaptacaoLote $lote,
        CaptacaoPedidoPorLojaSaidaFisicaService $labelsSaida,
        int $idOrigemDemanda,
    ): array {
        $origemFiscal = $labelsSaida->labelUnidadePorId($lote, $idOrigemDemanda) ?? '—';
        $itens = [];

        foreach ($vinculo->linhas as $linha) {
            $qtd = (float) $linha->qtd_um;
            if ($qtd <= 0) {
                continue;
            }

            $itens[] = [
                'fruta_nome' => (string) ($linha->fruta?->nome ?? 'Fruta'),
                'qtd_formatada' => $this->formatarQuantidadeUm(
                    $qtd,
                    mb_strtoupper((string) ($linha->fruta?->unidade_medicao ?? 'UM'), 'UTF-8'),
                ),
                'preco_venda' => $linha->preco_venda !== null
                    ? number_format((float) $linha->preco_venda, 2, ',', '.')
                    : null,
                'origem_fiscal_nome' => $origemFiscal,
            ];
        }

        if ($itens === []) {
            return [];
        }

        return [[
            'loja_nome' => 'Total da demanda (sem vínculo por loja nas linhas)',
            'ordem' => null,
            'itens' => $itens,
        ]];
    }

    private function formatarQuantidadeUm(float $qtd, string $unidadeMedicao): string
    {
        return rtrim(rtrim(number_format($qtd, 3, '.', ''), '0'), '.')
            .' '.$unidadeMedicao;
    }

    /**
     * @return array<string, mixed>
     */
    private function acoesVenda(CaptacaoLote $lote, CaptacaoLoteMovimentacao $vinculo, CaptacaoDemandaStatus $status): array
    {
        return [
            'url_efetivar' => route('admin.captacao.lotes.demandas.venda.efetivar', [$lote, $vinculo]),
            'url_cigam' => route('admin.captacao.lotes.demandas.venda.cigam', [$lote, $vinculo]),
            'pode_efetivar' => $status !== CaptacaoDemandaStatus::Concluido,
            'pode_cigam' => $status !== CaptacaoDemandaStatus::Concluido,
        ];
    }

    private function estadoClasseCard(CaptacaoDemandaStatus $status): string
    {
        return match ($status) {
            CaptacaoDemandaStatus::Aberto => 'nao_iniciado',
            CaptacaoDemandaStatus::Iniciado => 'em_andamento',
            CaptacaoDemandaStatus::Concluido => 'concluido',
        };
    }

    private function movimentacaoSaidaTransferencia(CaptacaoLoteMovimentacao $vinculo): ?Movimentacao
    {
        $transferenciaOrigemId = (int) ($vinculo->transferencia_origem_id ?? 0);
        if ($transferenciaOrigemId <= 0) {
            return null;
        }

        return Movimentacao::query()
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->first();
    }

    private function rotuloStatusTransferenciaSb(Movimentacao $saida): string
    {
        return match ($saida->status_transferencia) {
            StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value => 'Aguardando recebimento',
            StatusTransferenciaOperacional::RECEBIDA_CONFORME->value => 'Recebida conforme',
            StatusTransferenciaOperacional::CANCELADA->value => 'Cancelada',
            default => 'Em processamento',
        };
    }
}
