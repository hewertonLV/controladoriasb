@extends('layouts.app')

@section('title', 'Matriz de captação')
@section('page-title', 'Matriz — '.$lote->unidadeGalpao->nome)

@push('head')
    <style>
        #captacao-matriz {
            table-layout: fixed;
            --captacao-matriz-col-zebra: #eef0f8;
            --captacao-matriz-bloqueada: #e4e5f8;
            --captacao-matriz-bloqueada-zebra: #d8dbf0;
            --captacao-matriz-total: #e0e3f0;
        }

        [data-bs-theme='dark'] #captacao-matriz {
            --captacao-matriz-col-zebra: #2a2d3d;
            --captacao-matriz-bloqueada: #343a55;
            --captacao-matriz-bloqueada-zebra: #3d4460;
            --captacao-matriz-total: #3d4460;
        }

        #captacao-matriz .captacao-matriz-col-zebra {
            --highdmin-table-bg: var(--captacao-matriz-col-zebra);
            --highdmin-table-accent-bg: var(--captacao-matriz-col-zebra);
            background-color: var(--captacao-matriz-col-zebra) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-col-zebra) !important;
            border-color: var(--captacao-matriz-col-zebra) !important;
        }

        #captacao-matriz thead th.captacao-matriz-col-loja {
            width: 13rem;
            min-width: 11rem;
        }

        #captacao-matriz .captacao-numero-pedido {
            font-size: 0.7rem;
            padding: 0.1rem 0.25rem;
            min-height: 1.35rem;
            margin-top: 0.2rem;
        }

        #captacao-matriz .captacao-matriz-loja-nome {
            font-size: 0.85rem;
            line-height: 1.2;
        }

        #captacao-matriz thead th.captacao-matriz-col-fruta {
            vertical-align: bottom;
            text-align: center;
            width: 4.5rem;
            min-width: 4.5rem;
            height: auto;
            padding: 0.5rem 0.25rem;
            overflow: visible;
        }

        #captacao-matriz .captacao-matriz-fruta-nome {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            overflow: visible;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.15;
        }

        #captacao-matriz tbody td:not(:first-child) {
            width: 4.5rem;
            max-width: 4.75rem;
            padding: 0.2rem;
            vertical-align: middle;
        }

        #captacao-matriz .captacao-matriz-celula-stack {
            display: flex;
            flex-direction: column;
            gap: 0.12rem;
        }

        #captacao-matriz .captacao-celula-preco {
            font-size: 0.65rem;
            padding: 0.08rem 0.12rem;
            min-height: 1.35rem;
        }

        #captacao-matriz .captacao-celula {
            min-width: 2.25rem;
            padding: 0.15rem 0.2rem;
            text-align: center;
        }

        #captacao-matriz td.captacao-matriz-celula-bloqueada {
            --highdmin-table-bg: var(--captacao-matriz-bloqueada);
            --highdmin-table-accent-bg: var(--captacao-matriz-bloqueada);
            background-color: var(--captacao-matriz-bloqueada) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-bloqueada) !important;
            border-color: var(--captacao-matriz-bloqueada) !important;
            color: #313a46;
            text-align: center;
            vertical-align: middle;
        }

        #captacao-matriz td.captacao-matriz-celula-bloqueada.captacao-matriz-col-zebra {
            background-color: var(--captacao-matriz-bloqueada-zebra) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-bloqueada-zebra) !important;
            border-color: var(--captacao-matriz-bloqueada-zebra) !important;
        }

        [data-bs-theme='dark'] #captacao-matriz td.captacao-matriz-celula-bloqueada {
            color: var(--highdmin-body-color);
        }

        #captacao-matriz .captacao-matriz-col-zebra .captacao-celula {
            background-color: #fff;
        }

        [data-bs-theme='dark'] #captacao-matriz .captacao-matriz-col-zebra .captacao-celula {
            background-color: var(--highdmin-secondary-bg);
        }

        #captacao-matriz tr.matriz-row-loja-concluida .captacao-celula:disabled {
            background-color: var(--captacao-matriz-bloqueada);
            opacity: 1;
            cursor: not-allowed;
        }

        [data-bs-theme='dark'] #captacao-matriz tr.matriz-row-loja-concluida .captacao-celula:disabled {
            background-color: var(--highdmin-secondary-bg);
        }

        #captacao-matriz .captacao-matriz-sem-vinculo {
            display: inline-block;
            color: var(--bs-danger);
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1;
        }

        #captacao-matriz tr#matriz-row-totais td {
            font-weight: 700;
            background-color: var(--captacao-matriz-total) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-total) !important;
            border-color: var(--captacao-matriz-total) !important;
            color: #313a46;
            text-align: center;
        }

        [data-bs-theme='dark'] #captacao-matriz tr#matriz-row-totais td {
            color: var(--highdmin-body-color);
        }

        #captacao-matriz tr#matriz-row-totais td:first-child {
            text-align: left;
        }

        #captacao-matriz tr#matriz-row-adicionar td {
            background-color: #f8f9fc !important;
            box-shadow: inset 0 0 0 9999px #f8f9fc !important;
            border-color: #f8f9fc !important;
        }

        [data-bs-theme='dark'] #captacao-matriz tr#matriz-row-adicionar td {
            background-color: #2a2d3d !important;
            box-shadow: inset 0 0 0 9999px #2a2d3d !important;
            border-color: #2a2d3d !important;
        }

        #captacao-matriz-rotas {
            font-size: 0.85rem;
        }

        #captacao-matriz-rotas .matriz-rota-select {
            min-width: 10rem;
        }

        #captacao-matriz-ordem {
            font-size: 0.85rem;
        }

        #captacao-matriz-ordem .matriz-ordem-select {
            min-width: 4.5rem;
            width: auto;
        }

        #captacao-matriz-ordem .matriz-rota-motorista {
            width: 100%;
            font-size: 0.8rem;
        }

        #captacao-matriz-ordem .matriz-rota-veiculo {
            width: 100%;
            font-size: 0.8rem;
        }

        #captacao-matriz-ordem .matriz-rota-cabecalho {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.35rem;
            min-width: 11rem;
            max-width: 14rem;
        }

        #captacao-matriz-ordem .matriz-rota-cabecalho-campos {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
    </style>
@endpush

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', [
        'lote' => $lote,
        'modoCabecalho' => 'matriz',
    ])


    @if ($clientes->isEmpty() && $clientesDisponiveis->isEmpty())
        <div class="alert alert-warning">
            Nenhuma loja com frutas vinculadas neste faturamento.
            <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $lote->id_unidade_negocio_faturamento]) }}">Configure frutas por loja</a>
            antes de montar a captação.
        </div>
    @endif

    <div class="card">
        <div class="card-header pb-0 border-bottom-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $aba === 'quantidade' ? 'active' : '' }}"
                       href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'quantidade']) }}"
                       role="tab">
                        Quantidade
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $aba === 'rotas' ? 'active' : '' }}"
                       href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']) }}"
                       role="tab">
                        Rotas
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $aba === 'por-rota' ? 'active' : '' }}"
                       href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'por-rota']) }}"
                       role="tab">
                        Por rota
                    </a>
                </li>
                @if ($lote->status->exibeAbaArquivoCiganTransferencia())
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $aba === 'arquivo-cigan' ? 'active' : '' }}"
                           href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']) }}"
                           role="tab">
                            Arquivo Cigan
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade {{ $aba === 'quantidade' ? 'show active' : '' }}" id="matriz-tab-quantidade" role="tabpanel">
                <div class="card-body table-responsive border-top-0 pt-3">
                    <table class="table table-bordered table-sm" id="captacao-matriz">
                <thead>
                <tr id="matriz-header-row">
                    <th class="captacao-matriz-col-loja">Loja</th>
                    @foreach ($frutas as $fruta)
                        <th @class(['captacao-matriz-col-fruta', 'captacao-matriz-col-zebra' => $loop->odd]) data-fruta-id="{{ $fruta->id }}">
                            <span class="captacao-matriz-fruta-nome" title="{{ $fruta->nome }}">{{ $fruta->nome }}</span>
                        </th>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <th class="text-center text-nowrap" style="min-width:7rem">Conclusão</th>
                    @endif
                </tr>
                </thead>
                <tbody id="matriz-body">
                @foreach ($clientes as $cliente)
                    @php
                        $frutasCliente = $frutasPorCliente[$cliente->id] ?? [];
                        $pedidoLinha = $pedidosPorCliente->get($cliente->id);
                        $linhaConcluida = (bool) $pedidoLinha?->captacao_concluida;
                    @endphp
                    <tr class="matriz-row-loja @if($linhaConcluida) matriz-row-loja-concluida @endif" data-cliente-id="{{ $cliente->id }}" data-captacao-concluida="{{ $linhaConcluida ? '1' : '0' }}">
                        <td class="text-nowrap">
                            <p class="mb-0 captacao-matriz-loja-nome fw-semibold">{{ $cliente->fantasia ?: $cliente->razao_social }}</p>
                            @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                                <input type="text"
                                       class="form-control form-control-sm captacao-numero-pedido"
                                       maxlength="60"
                                       placeholder="Nº pedido"
                                       data-cliente="{{ $cliente->id }}"
                                       data-url="{{ route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $cliente]) }}"
                                       value="{{ $pedidoLinha?->numero_pedido ?? '' }}"
                                       @disabled($linhaConcluida)>
                            @elseif ($pedidoLinha?->numero_pedido)
                                <span class="text-muted small d-block mt-1">Pedido {{ $pedidoLinha->numero_pedido }}</span>
                            @endif
                        </td>
                        @foreach ($frutas as $fruta)
                            @php
                                $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
                                $item = $pedido?->itens->firstWhere('id_fruta', $fruta->id);
                                $temVinculo = in_array($fruta->id, $frutasCliente, true);
                                $podeEditarQty = $temVinculo && ! $linhaConcluida && $lote->status->permiteEdicaoQuantidadeCaptacao();
                                $podeEditarPreco = $temVinculo && $lote->status->permiteEdicaoPreco();
                            @endphp
                            <td @class([
                                'captacao-matriz-celula-bloqueada' => ! $temVinculo || ($linhaConcluida && ! $podeEditarPreco),
                                'captacao-matriz-col-zebra' => $loop->odd,
                            ])>
                                @if ($temVinculo)
                                    @php
                                        $precoDigitos = ($item?->preco_venda !== null && (float) $item->preco_venda > 0)
                                            ? (string) (int) round(((float) $item->preco_venda) * 100)
                                            : '';
                                        $precoExibicao = $precoDigitos !== ''
                                            ? number_format((int) $precoDigitos / 100, 2, ',', '.')
                                            : '';
                                    @endphp
                                    <div class="captacao-matriz-celula-stack">
                                        <input type="number"
                                               class="form-control form-control-sm captacao-celula captacao-celula-qty"
                                               step="1"
                                               min="0"
                                               data-lote="{{ $lote->id }}"
                                               data-cliente="{{ $cliente->id }}"
                                               data-fruta="{{ $fruta->id }}"
                                               data-version="{{ $item?->version ?? '' }}"
                                               value="{{ $item ? (int) $item->quantidade : '' }}"
                                               title="Quantidade"
                                               @disabled(! $podeEditarQty)
                                               @readonly(! $podeEditarQty)>
                                        <input type="text"
                                               class="form-control form-control-sm captacao-celula captacao-celula-preco"
                                               inputmode="numeric"
                                               autocomplete="off"
                                               placeholder="0,00"
                                               data-lote="{{ $lote->id }}"
                                               data-cliente="{{ $cliente->id }}"
                                               data-fruta="{{ $fruta->id }}"
                                               data-version="{{ $item?->version ?? '' }}"
                                               data-raw-digitos="{{ $precoDigitos }}"
                                               value="{{ $precoExibicao }}"
                                               title="Preço (R$)"
                                               @disabled(! $podeEditarPreco)
                                               @readonly(! $podeEditarPreco)>
                                    </div>
                                @else
                                    <span class="captacao-matriz-sem-vinculo" title="Fruta não vinculada a esta loja" aria-label="Sem vínculo">×</span>
                                @endif
                            </td>
                        @endforeach
                        @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                            <td class="text-center align-middle">
                                <button type="button"
                                        class="btn btn-sm {{ $pedidoLinha?->captacao_concluida ? 'btn-success' : 'btn-outline-secondary' }} btn-matriz-concluir"
                                        data-url="{{ route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $cliente]) }}"
                                        data-concluida="{{ $pedidoLinha?->captacao_concluida ? '1' : '0' }}"
                                        title="{{ $pedidoLinha?->captacao_concluida ? 'Reabrir captação desta loja' : 'Concluir captação desta loja' }}">
                                    {{ $pedidoLinha?->captacao_concluida ? 'Reabrir' : 'Concluir' }}
                                </button>
                            </td>
                        @endif
                    </tr>
                @endforeach

                @if ($clientes->isNotEmpty())
                <tr id="matriz-row-totais">
                    <td class="text-nowrap">Total</td>
                    @foreach ($frutas as $fruta)
                        <td class="matriz-total-celula" data-fruta-id="{{ $fruta->id }}">
                            @php $totalFruta = (float) ($totaisPorFruta[$fruta->id] ?? 0); @endphp
                            {{ $totalFruta > 0 ? (int) $totalFruta : '' }}
                        </td>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <td></td>
                    @endif
                </tr>
                @endif

                <tr id="matriz-row-adicionar">
                    <td>
                        <select id="select-nova-loja" class="form-select form-select-sm" @disabled($clientesDisponiveis->isEmpty())>
                            <option value="">Selecione a loja…</option>
                            @foreach ($clientesDisponiveis as $disponivel)
                                <option value="{{ $disponivel->id }}">{{ $disponivel->fantasia ?: $disponivel->razao_social }}</option>
                            @endforeach
                        </select>
                    </td>
                    @foreach ($frutas as $fruta)
                        <td @class(['captacao-matriz-celula-bloqueada', 'captacao-matriz-col-zebra' => $loop->odd])></td>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <td></td>
                    @endif
                </tr>
                </tbody>
            </table>
                </div>
            </div>

            <div class="tab-pane fade {{ $aba === 'rotas' ? 'show active' : '' }}" id="matriz-tab-rotas" role="tabpanel">
                <div class="card-body table-responsive border-top-0 pt-3">
                    <p class="text-muted small mb-2">
                        Itens com quantidade captada na aba Quantidade. A rota é vinculada por loja (mesma rota para todos os itens da loja).
                        Pode ser preenchida a qualquer momento até as vendas serem finalizadas.
                        @if ($lote->carteira)
                            <span class="d-block mt-1">Carteira do lote: <strong>{{ $lote->carteira->nome }}</strong> — só aparecem rotas cadastradas nesta carteira.</span>
                        @endif
                    </p>
                    @if ($rotas->isEmpty())
                        <div class="alert alert-warning py-2 small mb-3">
                            Nenhuma rota ativa cadastrada para esta carteira.
                            @can('captacao.rota.editar')
                                <a href="{{ $urlRotasCadastro }}" class="alert-link">Cadastrar rota</a>
                            @endcan
                        </div>
                    @endif
                    <table class="table table-bordered table-sm align-middle" id="captacao-matriz-rotas">
                        <thead>
                        <tr>
                            <th style="min-width:11rem">Loja</th>
                            <th>Item</th>
                            <th class="text-end" style="width:6rem">Qtd (UM)</th>
                            <th class="text-end" style="width:7rem">Preço</th>
                            <th style="min-width:12rem">Rota</th>
                        </tr>
                        </thead>
                        <tbody id="matriz-rotas-body">
                            @include('admin.captacao.matriz._rotas-tbody', [
                                'gruposRotas' => $gruposRotas,
                                'rotas' => $rotas,
                                'lote' => $lote,
                            ])
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade {{ $aba === 'por-rota' ? 'show active' : '' }}" id="matriz-tab-por-rota" role="tabpanel">
                <div class="card-body table-responsive border-top-0 pt-3">
                    <p class="text-muted small mb-2">
                        Lojas com rota vinculada e quantidade captada. Defina a ordem de carregamento dentro de cada rota; pode ser ajustada até as vendas serem finalizadas.
                    </p>
                    <table class="table table-bordered table-sm align-middle" id="captacao-matriz-ordem">
                        <thead>
                        <tr>
                            <th style="min-width:12rem">Rota</th>
                            <th style="width:8rem">Ordem de Carregamento</th>
                            <th style="min-width:11rem">Loja</th>
                            <th>Item</th>
                            <th class="text-end" style="width:6rem">Qtd (UM)</th>
                        </tr>
                        </thead>
                        <tbody id="matriz-ordem-body">
                            @include('admin.captacao.matriz._ordem-carregamento-tbody', [
                                'gruposOrdemCarregamento' => $gruposOrdemCarregamento,
                                'lote' => $lote,
                                'veiculos' => $veiculos,
                                'rotas' => $rotas,
                            ])
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($lote->status->exibeAbaArquivoCiganTransferencia())
                <div class="tab-pane fade {{ $aba === 'arquivo-cigan' ? 'show active' : '' }}" id="matriz-tab-arquivo-cigan" role="tabpanel">
                    <div class="card-body border-top-0 pt-4 pb-4">
                        <p class="text-muted small mb-3">
                            Arquivo TXT no layout <strong>EDI NF Cigam</strong> (registros <code>N</code> + <code>I</code>),
                            com as quantidades <strong>a receber</strong> do Romaneio 2 — Abastecimento.
                            Informe o <strong>HUB de origem</strong> (saída física) antes do download. Cliente/cobrança no TXT = código Cigam do <strong>cliente vinculado</strong> à unidade de faturamento
                            @if ($lote->unidadeFaturamento?->clientePrincipal)
                                (<strong>{{ $lote->unidadeFaturamento->clientePrincipal->razao_social }}</strong>, Cigam {{ $lote->unidadeFaturamento->clientePrincipal->id_cigam }}).
                            @else
                                — cadastre o <strong>código do cliente</strong> em {{ $lote->unidadeFaturamento?->nome ?? 'faturamento' }}.
                            @endif
                            Número da NF (pos. 9–15): <strong>em branco</strong> (Cigan numera pela série). Tipo de operação (20–24 / 372–376): <strong>{{ app(\App\Services\Captacao\CiganEdiNfTransferenciaGerador::class)->tipoOperacaoCigam() }}</strong> (transferência).
                            Transportadora (132–137): <strong>{{ app(\App\Services\Captacao\CiganEdiNfTransferenciaGerador::class)->codigoTransportadoraCigam() }}</strong>.
                            Entrada/Saída (283): <strong>S</strong>. Condição de pagamento (316–318): <strong>em branco</strong>.
                            Data emissão e entrada (26–33 e 35–42): <strong>{{ now()->format('d/m/Y') }}</strong>.
                        </p>

                        <div class="row g-3 mb-3">
                            <div class="col-lg-6">
                                @can(\App\Enums\Permissions::CAPTACAO_LOTE_TRANSFERENCIA_INICIAR)
                                    <form method="post" action="{{ route('admin.captacao.lotes.hub-origem-cigan.update', $lote) }}" class="row g-2 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-12">
                                            <label class="form-label" for="hub-origem-cigan">Unidade HUB de origem</label>
                                            <select name="id_unidade_negocio_hub_origem" id="hub-origem-cigan" class="form-select @error('id_unidade_negocio_hub_origem') is-invalid @enderror" required>
                                                <option value="">Selecione o HUB…</option>
                                                @foreach ($hubsDisponiveis as $hub)
                                                    <option value="{{ $hub->id }}" @selected((int) old('id_unidade_negocio_hub_origem', $lote->id_unidade_negocio_hub_origem) === $hub->id)>
                                                        {{ $hub->nome }} (Cigam {{ $hub->id_cigam }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('id_unidade_negocio_hub_origem')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-soft-primary btn-sm">Salvar HUB</button>
                                        </div>
                                    </form>
                                @endcan
                                @if ($lote->unidadeHubOrigem)
                                    <p class="small mb-0 mt-2 text-success">
                                        HUB atual: <strong>{{ $lote->unidadeHubOrigem->nome }}</strong>
                                        (Cigam {{ $lote->unidadeHubOrigem->id_cigam }}) — saída física da operação; destino fiscal = faturamento {{ $lote->unidadeFaturamento?->nome }}.
                                    </p>
                                @endif
                            </div>
                            <div class="col-lg-6">
                                @can(\App\Enums\Permissions::CAPTACAO_LOTE_TRANSFERENCIA_INICIAR)
                                    @if ($lote->id_unidade_negocio_hub_origem)
                                        <a href="{{ route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote) }}"
                                           class="btn btn-primary btn-sm">
                                            <i class="ri-download-2-line me-1"></i> Baixar arquivo TXT (Cigan)
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-primary btn-sm" disabled title="Selecione e salve o HUB de origem">
                                            <i class="ri-download-2-line me-1"></i> Baixar arquivo TXT (Cigan)
                                        </button>
                                        <p class="small text-muted mb-0 mt-1">Salve o HUB de origem para habilitar o download.</p>
                                    @endif
                                @else
                                    <p class="small text-warning mb-0">Sem permissão para baixar o arquivo de transferência.</p>
                                @endcan
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Fruta</th>
                                    <th class="text-end">A receber (UM)</th>
                                    <th class="text-end">A receber (kg)</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($romaneioAbastecimento as $linha)
                                    <tr @class(['table-light' => (float) $linha['a_receber_um'] <= 0])>
                                        <td>{{ $linha['fruta_nome'] }}</td>
                                        <td class="text-end">{{ $linha['a_receber_um_formatado'] }} {{ $linha['unidade_medicao'] }}</td>
                                        <td class="text-end">{{ $linha['a_receber_kg_formatado'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted">Sem linhas no romaneio de abastecimento.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mt-2 mb-0">
                            Somente itens com «a receber» &gt; 0 entram no TXT. Impostos podem ser calculados pelo Cigan na importação.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const urlCelula = @json(route('admin.captacao.lotes.celula.update', $lote));
    const urlEstado = @json(route('admin.captacao.lotes.matriz.estado', $lote));
    const urlAdicionarLoja = @json(route('admin.captacao.lotes.matriz.adicionar-loja', $lote));
    const urlRotasCadastro = @json($urlRotasCadastro);
    let rotasOptions = @json($rotas->map(fn ($r) => ['id' => $r->id, 'nome' => $r->nome, 'id_veiculo' => $r->id_veiculo])->values());
    let veiculosOptions = @json($veiculos->map(fn ($v) => ['id' => $v->id, 'id_sbs' => $v->id_sbs, 'nome' => $v->nome])->values());
    const loteId = @json($lote->id);
    const emCaptacao = @json($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento);
    const permiteEdicaoQuantidade = @json($lote->status->permiteEdicaoQuantidadeCaptacao());
    const permiteEdicaoPreco = @json($lote->status->permiteEdicaoPreco());
    const permiteVinculoRota = @json($lote->status->permiteEdicaoVinculoRota());
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const badge = document.getElementById('matriz-sync-badge');
    const selectLoja = document.getElementById('select-nova-loja');
    let matrizVersion = null;
    let layoutHash = @json($layoutHash);
    let salvando = false;

    function setBadge(estado) {
        if (!badge) return;
        badge.textContent = estado;
        badge.className = 'badge ' + ({
            sincronizado: 'bg-success',
            pendente: 'bg-warning text-dark',
            sincronizando: 'bg-info',
            erro: 'bg-danger',
        }[estado] || 'bg-secondary');
    }

    async function mensagemErroResposta(res) {
        const data = await res.json().catch(() => ({}));
        if (data.message) {
            return data.message;
        }
        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }

        return 'Não foi possível concluir a operação.';
    }

    function mostrarErro(mensagem, titulo = 'Atenção') {
        if (typeof window.AdminConfirm?.alert === 'function') {
            const ok = window.AdminConfirm.alert({
                title: titulo,
                message: mensagem,
                variant: 'warning',
                confirmLabel: 'Entendi',
            });

            if (ok !== false) {
                return;
            }
        }

        console.error('[Captacao matriz]', titulo, mensagem);
    }

    function headersJson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        };
    }

    document.querySelectorAll('.btn-matriz-concluir').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const novaConclusao = this.dataset.concluida === '1' ? '0' : '1';
            const row = this.closest('tr.matriz-row-loja');
            const clienteId = row?.dataset.clienteId;
            setBadge('sincronizando');
            try {
                const res = await fetch(this.dataset.url, {
                    method: 'POST',
                    headers: headersJson(),
                    body: JSON.stringify({ captacao_concluida: novaConclusao === '1' }),
                });
                if (!res.ok) {
                    mostrarErro(await mensagemErroResposta(res));
                    setBadge('erro');
                    return;
                }
                const data = await res.json();
                if (clienteId) {
                    aplicarConclusaoLinha(clienteId, !!data.captacao_concluida);
                }
                setBadge('sincronizado');
            } catch (e) {
                mostrarErro('Erro ao atualizar conclusão.');
                setBadge('erro');
            }
        });
    });

    selectLoja?.addEventListener('change', async function () {
        const idCliente = this.value;
        if (!idCliente) return;

        setBadge('sincronizando');
        this.disabled = true;

        try {
            const res = await fetch(urlAdicionarLoja, {
                method: 'POST',
                headers: headersJson(),
                body: JSON.stringify({ id_cliente: Number(idCliente) }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível adicionar a loja');
                this.disabled = false;
                this.value = '';
                setBadge('erro');
                return;
            }

            window.location.reload();
        } catch (e) {
            mostrarErro('Erro ao adicionar loja.');
            this.disabled = false;
            this.value = '';
            setBadge('erro');
        }
    });

    async function salvarCelula(origem, payload) {
        const stack = stackFromInput(origem);
        const qty = stack?.querySelector('.captacao-celula-qty');
        if (!qty || qty.disabled || qty.readOnly) {
            return;
        }
        setBadge('sincronizando');
        salvando = true;
        try {
            const res = await fetch(urlCelula, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                stack.querySelectorAll('.captacao-celula').forEach((inp) => inp.classList.add('is-invalid'));
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res), 'Captação indisponível');
                return;
            }
            const data = await res.json();
            aplicarVersionCelula(stack, data.item.version);
            stack.querySelectorAll('.captacao-celula').forEach((inp) => {
                inp.classList.remove('is-invalid');
                inp.classList.add('is-valid');
            });
            setBadge('sincronizado');
            atualizarTotais();
        } catch (e) {
            stack.querySelectorAll('.captacao-celula').forEach((inp) => inp.classList.add('is-invalid'));
            setBadge('erro');
        } finally {
            salvando = false;
        }
    }

    function stackFromInput(input) {
        return input.closest('.captacao-matriz-celula-stack');
    }

    function aplicarVersionCelula(stack, version) {
        stack.querySelectorAll('[data-version]').forEach((el) => {
            el.dataset.version = String(version);
        });
    }

    function digitosPreco(valor) {
        return String(valor ?? '').replace(/\D/g, '');
    }

    function formatarPrecoBr(digitos) {
        if (!digitos) {
            return '';
        }

        const n = parseInt(digitos, 10);
        const reais = Math.floor(n / 100);
        const centavos = String(n % 100).padStart(2, '0');
        const reaisFmt = reais.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return `${reaisFmt},${centavos}`;
    }

    function precoValorNumerico(input) {
        const digitos = digitosPreco(input.dataset.rawDigitos ?? input.value);
        if (!digitos) {
            return null;
        }

        return (parseInt(digitos, 10) / 100).toFixed(2);
    }

    function aplicarFormatoPreco(input) {
        let digitos = digitosPreco(input.value);
        if (digitos.length > 12) {
            digitos = digitos.slice(0, 12);
        }
        input.dataset.rawDigitos = digitos;
        input.value = formatarPrecoBr(digitos);
    }

    function payloadFromCelula(stack) {
        const qty = stack.querySelector('.captacao-celula-qty');
        const preco = stack.querySelector('.captacao-celula-preco');

        return {
            id_cliente: Number(qty.dataset.cliente),
            id_fruta: Number(qty.dataset.fruta),
            quantidade: qty.value === '' ? 0 : Number(qty.value),
            preco_venda: precoValorNumerico(preco),
            version: qty.dataset.version ? Number(qty.dataset.version) : null,
        };
    }

    function bindCelulas() {
        document.querySelectorAll('.captacao-celula-qty:not(:disabled)').forEach((input) => {
            if (input.dataset.bound) return;
            input.dataset.bound = '1';

            input.addEventListener('change', () => {
                if (input.disabled || input.readOnly) return;
                setBadge('pendente');
                salvarCelula(input, payloadFromCelula(stackFromInput(input)));
            });

            input.addEventListener('keydown', (e) => {
                if (input.disabled || input.readOnly) return;
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const delta = e.key === 'ArrowUp' ? 1 : -1;
                    const atual = input.value === '' ? 0 : Number(input.value);
                    input.value = Math.max(0, atual + delta);
                    setBadge('pendente');
                    const stack = stackFromInput(input);
                    const payload = payloadFromCelula(stack);
                    payload.incremento = delta;
                    delete payload.quantidade;
                    salvarCelula(input, payload);
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });

        document.querySelectorAll('.captacao-celula-preco:not(:disabled)').forEach((input) => {
            if (input.dataset.bound) return;
            input.dataset.bound = '1';

            if (input.dataset.rawDigitos && ! input.value) {
                input.value = formatarPrecoBr(input.dataset.rawDigitos);
            }

            input.addEventListener('input', () => {
                if (input.disabled || input.readOnly) return;
                aplicarFormatoPreco(input);
            });

            input.addEventListener('change', () => {
                if (input.disabled || input.readOnly) return;
                aplicarFormatoPreco(input);
                setBadge('pendente');
                salvarCelula(input, payloadFromCelula(stackFromInput(input)));
            });

            input.addEventListener('keydown', (e) => {
                if (input.disabled || input.readOnly) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });
    }

    function aplicarConclusaoLinha(clienteId, concluida) {
        const row = document.querySelector(`tr.matriz-row-loja[data-cliente-id="${clienteId}"]`);
        if (!row) return;

        row.dataset.captacaoConcluida = concluida ? '1' : '0';
        row.classList.toggle('matriz-row-loja-concluida', concluida);

        const btn = row.querySelector('.btn-matriz-concluir');
        if (btn) {
            btn.dataset.concluida = concluida ? '1' : '0';
            btn.classList.toggle('btn-success', concluida);
            btn.classList.toggle('btn-outline-secondary', !concluida);
            btn.textContent = concluida ? 'Reabrir' : 'Concluir';
            btn.title = concluida
                ? 'Reabrir captação desta loja'
                : 'Concluir captação desta loja';
        }

        row.querySelectorAll('.captacao-celula-qty').forEach((input) => {
            input.disabled = concluida || !permiteEdicaoQuantidade;
            input.readOnly = concluida || !permiteEdicaoQuantidade;
        });

        row.querySelectorAll('.captacao-celula-preco').forEach((input) => {
            input.disabled = !permiteEdicaoPreco;
            input.readOnly = !permiteEdicaoPreco;
        });

        const numero = row.querySelector('.captacao-numero-pedido');
        if (numero) {
            numero.disabled = concluida;
        }

        row.querySelectorAll('td.captacao-matriz-celula-bloqueada').forEach((td) => {
            if (td.querySelector('.captacao-matriz-sem-vinculo')) {
                return;
            }
            td.classList.toggle('captacao-matriz-celula-bloqueada', concluida && !permiteEdicaoPreco);
        });
    }

    function escHtml(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatarPrecoExibicao(valor) {
        if (valor === null || valor === undefined || parseFloat(valor) <= 0) {
            return '<span class="text-muted">—</span>';
        }

        const digitos = String(Math.round(parseFloat(valor) * 100));

        return escHtml(formatarPrecoBr(digitos));
    }

    function formatarQuantidadeExibicao(valor) {
        const n = parseFloat(valor);
        if (Number.isNaN(n) || n <= 0) {
            return '—';
        }

        return escHtml(String(Number.isInteger(n) ? n : n.toString()));
    }

    function optionsRotasHtml(selecionada) {
        if (!rotasOptions.length) {
            return `<option value="">Nenhuma rota nesta carteira</option>`;
        }

        let html = '<option value="">Selecione a rota…</option>';
        rotasOptions.forEach((rota) => {
            const selected = Number(selecionada) === Number(rota.id) ? ' selected' : '';
            html += `<option value="${rota.id}"${selected}>${escHtml(rota.nome)}</option>`;
        });

        return html;
    }

    function atualizarAlertaRotasVazias() {
        const alertaId = 'matriz-rotas-sem-cadastro';
        let alerta = document.getElementById(alertaId);
        const tabRotas = document.getElementById('matriz-tab-rotas');
        if (!tabRotas) {
            return;
        }

        if (rotasOptions.length) {
            alerta?.remove();
            return;
        }

        if (alerta) {
            return;
        }

        alerta = document.createElement('div');
        alerta.id = alertaId;
        alerta.className = 'alert alert-warning py-2 small mb-3';
        alerta.innerHTML = `Nenhuma rota ativa cadastrada para esta carteira. <a href="${escHtml(urlRotasCadastro)}" class="alert-link">Cadastrar rota</a>`;
        const tabela = document.getElementById('captacao-matriz-rotas');
        tabela?.parentElement?.insertBefore(alerta, tabela);
    }

    function renderRotasTabela(linhasRotas) {
        const tbody = document.getElementById('matriz-rotas-body');
        if (!tbody) {
            return;
        }

        if (!linhasRotas?.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-4">Nenhum item com quantidade informada. Use a aba <strong>Quantidade</strong> para captar pedidos.</td></tr>';
            return;
        }

        let html = '';
        linhasRotas.forEach((grupo) => {
            const rowspan = grupo.itens.length;
            grupo.itens.forEach((item, idx) => {
                html += `<tr class="matriz-rotas-row" data-cliente-id="${grupo.id_cliente}" data-fruta-id="${item.id_fruta}">`;
                if (idx === 0) {
                    html += `<td rowspan="${rowspan}" class="align-top text-nowrap"><span class="fw-semibold">${escHtml(grupo.loja_nome)}</span>`;
                    if (grupo.numero_pedido) {
                        html += `<div class="text-muted small">Pedido ${escHtml(grupo.numero_pedido)}</div>`;
                    }
                    html += '</td>';
                }
                html += `<td>${escHtml(item.fruta_nome)} <span class="text-muted small">(${escHtml(item.unidade_medicao)})</span></td>`;
                html += `<td class="text-end matriz-rotas-qty">${formatarQuantidadeExibicao(item.quantidade)}</td>`;
                html += `<td class="text-end matriz-rotas-preco">${formatarPrecoExibicao(item.preco_venda)}</td>`;
                if (idx === 0) {
                    if (permiteVinculoRota) {
                        const url = @json(url('admin/captacao/lotes/'.$lote->id.'/pedidos')) + `/${grupo.id_cliente}/rota`;
                        html += `<td rowspan="${rowspan}" class="align-top">`;
                        html += `<select class="form-select form-select-sm matriz-rota-select" data-cliente="${grupo.id_cliente}" data-url="${url}">`;
                        html += optionsRotasHtml(grupo.id_captacao_rota);
                        html += '</select></td>';
                    } else {
                        const rota = rotasOptions.find((r) => Number(r.id) === Number(grupo.id_captacao_rota));
                        html += `<td rowspan="${rowspan}" class="align-top">${escHtml(rota?.nome ?? '—')}</td>`;
                    }
                }
                html += '</tr>';
            });
        });

        tbody.innerHTML = html;
        atualizarAlertaRotasVazias();
    }

    function idsVeiculosOcupadosOutrasRotas(rotaId) {
        const ocupados = new Set();

        rotasOptions.forEach((rota) => {
            if (rota.id_veiculo && Number(rota.id) !== Number(rotaId)) {
                ocupados.add(Number(rota.id_veiculo));
            }
        });

        return ocupados;
    }

    function optionsVeiculosHtml(selecionado, rotaId) {
        const ocupados = idsVeiculosOcupadosOutrasRotas(rotaId);
        let html = '<option value="">Sem veículo vinculado</option>';

        veiculosOptions.forEach((veiculo) => {
            if (ocupados.has(Number(veiculo.id)) && Number(selecionado) !== Number(veiculo.id)) {
                return;
            }

            const selected = Number(selecionado) === Number(veiculo.id) ? ' selected' : '';
            html += `<option value="${veiculo.id}"${selected}>${escHtml(veiculo.nome)} (SBS ${escHtml(String(veiculo.id_sbs))})</option>`;
        });

        return html;
    }

    function atualizarSelectsVeiculoRotas(rotaEmEdicao = null) {
        document.querySelectorAll('.matriz-rota-veiculo').forEach((select) => {
            const rotaId = Number(select.dataset.rota);
            if (rotaEmEdicao !== null && rotaId === Number(rotaEmEdicao) && document.activeElement === select) {
                return;
            }

            const valorAtual = select.value === '' ? null : Number(select.value);
            select.innerHTML = optionsVeiculosHtml(valorAtual, rotaId);
            select.value = valorAtual ? String(valorAtual) : '';
        });
    }

    function optionsOrdemHtml(totalLojas, selecionada) {
        let html = '<option value="">—</option>';
        for (let n = 1; n <= totalLojas; n += 1) {
            const selected = Number(selecionada) === n ? ' selected' : '';
            html += `<option value="${n}"${selected}>${n}</option>`;
        }

        return html;
    }

    function renderOrdemCarregamento(grupos) {
        const tbody = document.getElementById('matriz-ordem-body');
        if (!tbody) {
            return;
        }

        if (!grupos?.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-4">Nenhuma loja com rota e quantidade informada. Use as abas <strong>Quantidade</strong> e <strong>Rotas</strong> primeiro.</td></tr>';
            return;
        }

        let html = '';
        const motoristaEmEdicao = document.querySelector('.matriz-rota-motorista:focus');
        const rotaMotoristaEmEdicao = motoristaEmEdicao?.dataset.rota ?? null;
        const valorMotoristaEmEdicao = motoristaEmEdicao?.value ?? '';
        const veiculoEmEdicao = document.querySelector('.matriz-rota-veiculo:focus');
        const rotaVeiculoEmEdicao = veiculoEmEdicao?.dataset.rota ?? null;
        const valorVeiculoEmEdicao = veiculoEmEdicao?.value ?? '';

        grupos.forEach((grupoRota) => {
            const rotaRowspan = grupoRota.lojas.reduce((acc, loja) => acc + loja.itens.length, 0);
            let rotaRenderizada = false;

            grupoRota.lojas.forEach((loja) => {
                const lojaRowspan = loja.itens.length;
                loja.itens.forEach((item, idx) => {
                    html += `<tr class="matriz-ordem-row" data-rota-id="${grupoRota.id_captacao_rota}" data-cliente-id="${loja.id_cliente}" data-fruta-id="${item.id_fruta}">`;
                    if (!rotaRenderizada) {
                        const urlMotorista = @json(url('admin/captacao/lotes/'.$lote->id.'/rotas')) + `/${grupoRota.id_captacao_rota}/motorista`;
                        const urlVeiculo = @json(url('admin/captacao/lotes/'.$lote->id.'/rotas')) + `/${grupoRota.id_captacao_rota}/veiculo`;
                        html += `<td rowspan="${rotaRowspan}" class="align-top">`;
                        html += '<div class="matriz-rota-cabecalho">';
                        html += `<span class="fw-semibold text-nowrap">${escHtml(grupoRota.rota_nome)}</span>`;
                        if (permiteVinculoRota) {
                            html += '<div class="matriz-rota-cabecalho-campos">';
                            html += `<input type="text" class="form-control form-control-sm matriz-rota-motorista" maxlength="120" placeholder="Motorista" data-rota="${grupoRota.id_captacao_rota}" data-url="${urlMotorista}" value="${escHtml(grupoRota.motorista_nome ?? '')}">`;
                            html += `<select class="form-select form-select-sm matriz-rota-veiculo" data-rota="${grupoRota.id_captacao_rota}" data-url="${urlVeiculo}">`;
                            html += optionsVeiculosHtml(grupoRota.id_veiculo, grupoRota.id_captacao_rota);
                            html += '</select></div>';
                        } else {
                            if (grupoRota.motorista_nome) {
                                html += `<span class="text-muted small">— ${escHtml(grupoRota.motorista_nome)}</span>`;
                            }
                            if (grupoRota.veiculo_rotulo) {
                                html += `<span class="text-muted small d-block">${escHtml(grupoRota.veiculo_rotulo)}</span>`;
                            }
                        }
                        html += '</div></td>';
                        rotaRenderizada = true;
                    }
                    if (idx === 0) {
                        if (permiteVinculoRota) {
                            const url = @json(url('admin/captacao/lotes/'.$lote->id.'/pedidos')) + `/${loja.id_cliente}/ordem-carregamento`;
                            html += `<td rowspan="${lojaRowspan}" class="align-top">`;
                            html += `<select class="form-select form-select-sm matriz-ordem-select" data-cliente="${loja.id_cliente}" data-rota="${grupoRota.id_captacao_rota}" data-total-lojas="${grupoRota.total_lojas}" data-url="${url}">`;
                            html += optionsOrdemHtml(grupoRota.total_lojas, loja.ordem_carregamento);
                            html += '</select></td>';
                        } else {
                            html += `<td rowspan="${lojaRowspan}" class="align-top">${escHtml(loja.ordem_carregamento ?? '—')}</td>`;
                        }
                        html += `<td rowspan="${lojaRowspan}" class="align-top text-nowrap fw-semibold">${escHtml(loja.loja_nome)}</td>`;
                    }
                    html += `<td>${escHtml(item.fruta_nome)} <span class="text-muted small">(${escHtml(item.unidade_medicao)})</span></td>`;
                    html += `<td class="text-end matriz-ordem-qty">${formatarQuantidadeExibicao(item.quantidade)}</td>`;
                    html += '</tr>';
                });
            });
        });

        tbody.innerHTML = html;

        if (rotaMotoristaEmEdicao) {
            const input = tbody.querySelector(`.matriz-rota-motorista[data-rota="${rotaMotoristaEmEdicao}"]`);
            if (input) {
                input.value = valorMotoristaEmEdicao;
                input.focus();
            }
        }

        if (rotaVeiculoEmEdicao) {
            const select = tbody.querySelector(`.matriz-rota-veiculo[data-rota="${rotaVeiculoEmEdicao}"]`);
            if (select) {
                select.value = valorVeiculoEmEdicao;
                select.focus();
            }
        }
    }

    async function salvarVeiculoRota(select) {
        if (select.disabled) {
            return;
        }

        setBadge('sincronizando');

        try {
            const res = await fetch(select.dataset.url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ id_veiculo: select.value === '' ? null : Number(select.value) }),
            });

            if (!res.ok) {
                select.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }

            select.classList.remove('is-invalid');
            select.classList.add('is-valid');
            setBadge('sincronizado');

            const payload = await res.json();
            const rotaId = Number(select.dataset.rota);
            const rota = rotasOptions.find((item) => Number(item.id) === rotaId);
            if (rota) {
                rota.id_veiculo = payload.id_veiculo ?? null;
            }
            atualizarSelectsVeiculoRotas(rotaId);
        } catch (e) {
            select.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    async function salvarMotoristaRota(input) {
        if (input.disabled) {
            return;
        }

        setBadge('sincronizando');

        try {
            const res = await fetch(input.dataset.url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ nome_motorista: input.value.trim() || null }),
            });

            if (!res.ok) {
                input.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }

            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            setBadge('sincronizado');
        } catch (e) {
            input.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    document.getElementById('matriz-ordem-body')?.addEventListener('change', (event) => {
        const select = event.target.closest('.matriz-rota-veiculo');
        if (!select) {
            return;
        }
        salvarVeiculoRota(select);
    });

    document.getElementById('matriz-ordem-body')?.addEventListener('blur', (event) => {
        const input = event.target.closest('.matriz-rota-motorista');
        if (!input) {
            return;
        }
        salvarMotoristaRota(input);
    }, true);

    document.getElementById('matriz-ordem-body')?.addEventListener('keydown', (event) => {
        const input = event.target.closest('.matriz-rota-motorista');
        if (!input) {
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            input.blur();
        }
    });

    async function salvarOrdemCarregamento(select) {
        if (select.disabled) {
            return;
        }

        const ordem = select.value === '' ? null : Number(select.value);
        setBadge('sincronizando');

        try {
            const res = await fetch(select.dataset.url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ ordem_carregamento: ordem }),
            });

            if (!res.ok) {
                select.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }

            select.classList.remove('is-invalid');
            select.classList.add('is-valid');
            select.blur();

            const estadoRes = await fetch(urlEstado, { headers: { 'Accept': 'application/json' } });
            if (estadoRes.ok) {
                const data = await estadoRes.json();
                matrizVersion = data.version;
                if (data.grupos_ordem_carregamento) {
                    renderOrdemCarregamento(data.grupos_ordem_carregamento);
                }
            }

            setBadge('sincronizado');
        } catch (e) {
            select.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    document.getElementById('matriz-ordem-body')?.addEventListener('change', (event) => {
        const select = event.target.closest('.matriz-ordem-select');
        if (!select) {
            return;
        }
        salvarOrdemCarregamento(select);
    });

    async function salvarRota(select) {
        if (select.disabled) {
            return;
        }

        const clienteId = select.dataset.cliente;
        const rotaId = select.value === '' ? null : Number(select.value);
        setBadge('sincronizando');

        try {
            const res = await fetch(select.dataset.url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ id_captacao_rota: rotaId }),
            });

            if (!res.ok) {
                select.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }

            select.classList.remove('is-invalid');
            select.classList.add('is-valid');
            document.querySelectorAll(`.matriz-rota-select[data-cliente="${clienteId}"]`).forEach((outro) => {
                if (outro !== select) {
                    outro.value = select.value;
                }
                outro.classList.remove('is-invalid');
                outro.classList.add('is-valid');
            });
            select.classList.add('is-valid');
            setBadge('sincronizado');
            pollEstado();
        } catch (e) {
            select.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    document.getElementById('matriz-rotas-body')?.addEventListener('change', (event) => {
        const select = event.target.closest('.matriz-rota-select');
        if (!select) {
            return;
        }
        salvarRota(select);
    });

    async function salvarNumeroPedido(input) {
        if (input.disabled) return;
        setBadge('sincronizando');
        try {
            const res = await fetch(input.dataset.url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ numero_pedido: input.value.trim() || null }),
            });
            if (!res.ok) {
                input.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            setBadge('sincronizado');
        } catch (e) {
            input.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    function bindNumeroPedido() {
        document.querySelectorAll('.captacao-numero-pedido:not([data-bound])').forEach((input) => {
            input.dataset.bound = '1';
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
            input.addEventListener('blur', () => salvarNumeroPedido(input));
        });
    }

    bindNumeroPedido();

    function atualizarTotais() {
        const rowTotais = document.getElementById('matriz-row-totais');
        if (!rowTotais) return;

        rowTotais.querySelectorAll('.matriz-total-celula[data-fruta-id]').forEach((cel) => {
            const frutaId = cel.dataset.frutaId;
            let total = 0;

            document.querySelectorAll(`.captacao-celula-qty[data-fruta="${frutaId}"]`).forEach((input) => {
                total += input.value === '' ? 0 : Number(input.value);
            });

            cel.textContent = total > 0 ? String(total) : '';
        });
    }

    async function pollEstado() {
        if (salvando) return;
        try {
            const res = await fetch(urlEstado, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.layout_hash && data.layout_hash !== layoutHash) {
                window.location.reload();
                return;
            }

            if (matrizVersion !== null && matrizVersion !== data.version) {
                aplicarEstado(data);
            } else if (matrizVersion === null) {
                aplicarEstado(data);
            }
            matrizVersion = data.version;
        } catch (e) { /* ignore */ }
    }

    function aplicarEstado(data) {
        Object.entries(data.celulas || {}).forEach(([key, cel]) => {
            const [clienteId, frutaId] = key.split('_');
            const qty = document.querySelector(
                `.captacao-celula-qty[data-cliente="${clienteId}"][data-fruta="${frutaId}"]`
            );
            const preco = document.querySelector(
                `.captacao-celula-preco[data-cliente="${clienteId}"][data-fruta="${frutaId}"]`
            );
            if (!qty || qty.disabled || qty.readOnly) return;
            if (document.activeElement === qty || document.activeElement === preco) return;

            qty.value = cel.quantidade > 0 ? Number(cel.quantidade) : '';
            qty.dataset.version = cel.version;

            if (preco) {
                if (cel.preco_venda !== null && parseFloat(cel.preco_venda) > 0) {
                    const digitos = String(Math.round(parseFloat(cel.preco_venda) * 100));
                    preco.dataset.rawDigitos = digitos;
                    preco.value = formatarPrecoBr(digitos);
                } else {
                    preco.dataset.rawDigitos = '';
                    preco.value = '';
                }
                preco.dataset.version = cel.version;
            }
        });

        Object.entries(data.pedidos || {}).forEach(([clienteId, pedido]) => {
            const row = document.querySelector(`tr.matriz-row-loja[data-cliente-id="${clienteId}"]`);
            if (!row) return;

            const concluidaLocal = row.dataset.captacaoConcluida === '1';
            const concluidaRemota = !!pedido.captacao_concluida;
            if (concluidaLocal !== concluidaRemota) {
                aplicarConclusaoLinha(clienteId, concluidaRemota);
            }

            const numeroInput = row.querySelector('.captacao-numero-pedido');
            if (numeroInput && document.activeElement !== numeroInput) {
                numeroInput.value = pedido.numero_pedido ?? '';
            }

            if (!document.querySelector(`.matriz-rota-select[data-cliente="${clienteId}"]:focus`)) {
                document.querySelectorAll(`.matriz-rota-select[data-cliente="${clienteId}"]`).forEach((select) => {
                    select.value = pedido.id_captacao_rota ? String(pedido.id_captacao_rota) : '';
                });
            }

            if (!document.querySelector(`.matriz-ordem-select[data-cliente="${clienteId}"]:focus`)) {
                document.querySelectorAll(`.matriz-ordem-select[data-cliente="${clienteId}"]`).forEach((select) => {
                    select.value = pedido.ordem_carregamento ? String(pedido.ordem_carregamento) : '';
                });
            }
        });

        if (Array.isArray(data.rotas)) {
            rotasOptions = data.rotas;
        }

        if (Array.isArray(data.veiculos)) {
            veiculosOptions = data.veiculos;
        }

        (data.rotas || []).forEach((rota) => {
            if (document.querySelector(`.matriz-rota-veiculo[data-rota="${rota.id}"]:focus`)) {
                return;
            }

            document.querySelectorAll(`.matriz-rota-veiculo[data-rota="${rota.id}"]`).forEach((select) => {
                select.value = rota.id_veiculo ? String(rota.id_veiculo) : '';
            });
        });

        if (data.linhas_rotas) {
            renderRotasTabela(data.linhas_rotas);
        } else {
            atualizarAlertaRotasVazias();
        }

        if (data.grupos_ordem_carregamento) {
            renderOrdemCarregamento(data.grupos_ordem_carregamento);
        }

        atualizarTotais();
    }

    bindCelulas();

    pollEstado();
    setInterval(pollEstado, 2000);
})();
</script>
@endpush
