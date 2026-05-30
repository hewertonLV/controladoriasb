@extends('layouts.app')

@section('title', 'Captação')
@section('page-title', 'Captação — '.$lote->unidadeGalpao->nome)

@push('head')
    <style>
        #captacao-matriz {
            table-layout: auto;
            width: max-content;
            min-width: 100%;
            margin-top: 0;
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

        #captacao-matriz thead th.captacao-matriz-col-loja,
        #captacao-matriz tbody td.captacao-matriz-col-loja {
            width: auto;
            white-space: nowrap;
            vertical-align: middle;
            text-align: left;
            padding: 0.2rem 0.35rem;
            overflow: visible;
        }

        #captacao-matriz thead th.captacao-matriz-col-loja {
            text-align: center;
        }

        #captacao-matriz .captacao-numero-pedido {
            font-size: 0.7rem;
            padding: 0.1rem 0.25rem;
            min-height: 1.35rem;
            margin-top: 0.2rem;
            width: auto;
            min-width: 6.25rem;
        }

        #captacao-matriz #select-nova-loja {
            width: auto;
            min-width: 6.25rem;
        }

        #captacao-matriz .captacao-matriz-loja-nome {
            font-size: 0.85rem;
            line-height: 1.2;
        }

        #captacao-matriz thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--highdmin-body-bg, #fff);
            box-shadow: 0 1px 0 var(--bs-border-color, #dee2e6);
            padding-top: 0;
            margin-top: 0;
            vertical-align: middle;
            text-align: center;
        }

        [data-bs-theme='dark'] #captacao-matriz thead th {
            background-color: var(--highdmin-secondary-bg, #1e2130);
        }

        #captacao-matriz thead th.captacao-matriz-col-zebra {
            background-color: var(--captacao-matriz-col-zebra) !important;
        }

        #captacao-matriz thead th.captacao-matriz-col-fruta {
            vertical-align: middle;
            text-align: center;
            width: 4.5rem;
            min-width: 4.5rem;
            height: auto;
            padding: 0 0.25rem;
            overflow: visible;
        }

        #captacao-matriz thead .captacao-matriz-fruta-nome {
            align-items: center;
            justify-content: center;
        }

        #captacao-matriz .captacao-matriz-fruta-nome {
            display: inline-flex;
            flex-direction: row;
            align-items: flex-end;
            justify-content: center;
            gap: 0.2rem;
            overflow: visible;
        }

        #captacao-matriz .captacao-matriz-fruta-linha {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            overflow: visible;
            font-size: 0.65rem;
            font-weight: 600;
            line-height: 1.1;
        }

        #captacao-matriz tbody td:not(.captacao-matriz-col-loja):not(.captacao-matriz-col-saida-fisica):not(.captacao-matriz-col-conclusao) {
            width: 4.5rem;
            min-width: 4.5rem;
            max-width: 4.5rem;
            padding: 0.2rem;
            vertical-align: middle;
        }

        #captacao-matriz .captacao-celula.is-valid,
        #captacao-matriz .captacao-celula-preco.is-valid,
        #captacao-matriz .captacao-numero-pedido.is-valid {
            border-color: var(--bs-success, #198754);
            background-image: none !important;
            padding-right: 0.2rem;
            box-shadow: 0 0 0 0.12rem rgba(25, 135, 84, 0.18);
        }

        #captacao-matriz .captacao-celula.is-invalid,
        #captacao-matriz .captacao-celula-preco.is-invalid,
        #captacao-matriz .captacao-numero-pedido.is-invalid {
            background-image: none !important;
            padding-right: 0.2rem;
        }

        .captacao-matriz-scroll-wrap {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            width: 100%;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .captacao-matriz-scroll-wrap.card-body {
            padding-top: 0;
            display: flex;
            flex-direction: column;
        }

        .captacao-matriz-scroll-wrap > .table-responsive {
            overflow: visible;
            width: max-content;
            min-width: 100%;
            max-width: none;
        }

        .captacao-matriz-page {
            display: flex;
            flex-direction: column;
            gap: 0;
            max-width: 100%;
            min-width: 0;
            max-height: calc(100dvh - 8.5rem);
            overflow: hidden;
        }

        .captacao-matriz-nav-card {
            flex: 0 0 auto;
        }

        .captacao-matriz-tabela-card {
            flex: 1 1 auto;
            min-height: 0;
            max-width: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }

        .captacao-matriz-tabela-card > .tab-content {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .captacao-matriz-tabela-card .tab-pane.show.active {
            display: flex !important;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }

        .captacao-matriz-tabela-card .tab-pane.show.active > .card-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
        }

        .captacao-matriz-concluir-wrap .btn:disabled {
            pointer-events: none;
        }

        #captacao-matriz .captacao-matriz-col-saida-fisica {
            vertical-align: top !important;
            width: auto;
            white-space: nowrap;
            padding: 0.25rem 0.35rem;
            box-sizing: border-box;
            overflow: visible;
        }

        #captacao-matriz .captacao-matriz-saida-opcoes {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            font-size: 0.72rem;
            line-height: 1.1;
            width: max-content;
        }

        #captacao-matriz .captacao-matriz-saida-opcao {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            margin: 0;
            cursor: pointer;
            white-space: nowrap;
        }

        #captacao-matriz .captacao-matriz-saida-opcao .form-check-input {
            margin-top: 0;
            flex-shrink: 0;
        }

        #captacao-matriz .captacao-matriz-saida-opcao span {
            white-space: nowrap;
        }

        #captacao-matriz thead th.captacao-matriz-col-saida-fisica {
            vertical-align: middle;
            text-align: center;
            width: auto;
            white-space: nowrap;
            padding: 0 0.25rem;
            overflow: visible;
        }

        #captacao-matriz thead th.captacao-matriz-col-conclusao {
            vertical-align: middle;
            text-align: center;
            padding-top: 0;
        }

        #captacao-matriz th.captacao-matriz-col-conclusao,
        #captacao-matriz td.captacao-matriz-col-conclusao {
            width: 7rem;
            min-width: 7rem;
            max-width: 7rem;
            overflow: hidden;
            box-sizing: border-box;
        }

        #captacao-matriz .captacao-matriz-acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            justify-content: center;
            align-items: center;
        }

        #captacao-matriz .captacao-matriz-acoes .btn-matriz-icon,
        .matriz-tab-pane-rota-vinculada .matriz-rota-acoes .btn-matriz-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem 0.4rem;
            line-height: 1;
        }

        #captacao-matriz .captacao-matriz-acoes .btn-matriz-icon i,
        .matriz-tab-pane-rota-vinculada .matriz-rota-acoes .btn-matriz-icon i {
            font-size: 1.05rem;
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

        #captacao-matriz-rotas .select2-container.matriz-rota-select2--salvo .select2-selection {
            border-color: var(--bs-success, #198754) !important;
            border-width: 2px !important;
            box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.28);
        }

        #captacao-matriz-rotas .select2-container.matriz-rota-select2--erro .select2-selection {
            border-color: var(--bs-danger, #dc3545) !important;
            border-width: 2px !important;
            box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.28);
        }

        .matriz-tab-pane-rota-vinculada {
            font-size: 0.85rem;
        }

        .matriz-tab-pane-rota-vinculada .matriz-ordem-select {
            min-width: 4.5rem;
            width: auto;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-motorista {
            width: 100%;
            font-size: 0.8rem;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-veiculo {
            width: 100%;
            font-size: 0.8rem;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-cabecalho {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.35rem;
            min-width: 11rem;
            max-width: 18rem;
        }

        .matriz-tab-pane-rota-vinculada .btn-matriz-rota-concluir--pendente {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .captacao-matriz-tooltip-pendencias .tooltip-inner {
            max-width: 20rem;
            text-align: left;
        }

        .admin-datatable-toast-host {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            max-width: min(22rem, calc(100vw - 2rem));
            pointer-events: none;
        }

        .admin-datatable-toast {
            pointer-events: auto;
            width: 100%;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.2);
            overflow: hidden;
            opacity: 0;
            transform: translateY(-0.35rem) scale(0.98);
            transition: opacity 0.28s ease, transform 0.28s ease;
        }

        .admin-datatable-toast.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .admin-datatable-toast.is-hiding {
            opacity: 0;
            transform: translateY(-0.35rem) scale(0.98);
        }

        .admin-datatable-toast__content {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.75rem 0.85rem 0.65rem;
        }

        .admin-datatable-toast__content > i:first-child {
            font-size: 1.15rem;
            line-height: 1.2;
            flex-shrink: 0;
            margin-top: 0.05rem;
        }

        .admin-datatable-toast__message {
            flex: 1 1 auto;
            font-size: 0.85rem;
            line-height: 1.35;
            white-space: pre-wrap;
        }

        .admin-datatable-toast__close {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            opacity: 0.75;
            flex-shrink: 0;
        }

        .admin-datatable-toast--warning {
            background: var(--bs-warning-bg-subtle, #fff3cd);
            border-color: var(--bs-warning-border-subtle, #ffe69c);
            color: var(--bs-warning-text-emphasis, #664d03);
        }

        .admin-datatable-toast--warning .admin-datatable-toast__content > i:first-child {
            color: var(--bs-warning, #ffc107);
        }

        .admin-datatable-toast--danger {
            background: var(--bs-danger-bg-subtle, #f8d7da);
            border-color: var(--bs-danger-border-subtle, #f1aeb5);
            color: var(--bs-danger-text-emphasis, #58151c);
        }

        .admin-datatable-toast--danger .admin-datatable-toast__content > i:first-child {
            color: var(--bs-danger, #dc3545);
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-cabecalho-campos {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-titulo {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            width: 100%;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-titulo-esq {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
            min-width: 0;
            flex: 1 1 auto;
            justify-content: flex-start;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-titulo-dir {
            flex: 0 0 auto;
            margin-left: auto;
        }

        .matriz-tab-pane-rota-vinculada .matriz-rota-acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }

        .captacao-matriz-nav-card .nav-tabs {
            border-bottom: 1px solid var(--bs-border-color, #dee2e6);
            margin-bottom: 0;
        }

        .captacao-matriz-nav-card .nav-tabs .nav-link {
            margin-bottom: -1px;
        }
    </style>
@endpush

@section('content')
    <x-admin.flash-messages />

    @if ($clientes->isEmpty() && $clientesDisponiveis->isEmpty())
        <div class="alert alert-warning">
            Nenhuma loja com frutas vinculadas neste faturamento.
            <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $lote->id_unidade_negocio_faturamento]) }}">Configure frutas por loja</a>
            antes de montar a captação.
        </div>
    @endif

    <div class="captacao-matriz-page">
    <div class="card mb-3 captacao-matriz-nav-card">
        <div class="card-body py-2 pb-1">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom">
                <p class="text-muted small mb-0">
                    {{ $lote->carteira?->nome }} · {{ $lote->unidadeGalpao?->nome }}
                    · <span class="badge {{ $lote->status->badgeListagem() }}">{{ $lote->status->label() }}</span>
                </p>
                @include('admin.captacao._concluir-captacao-lote', [
                    'lote' => $lote,
                    'podeConcluirCaptacaoLote' => $podeConcluirCaptacaoLote ?? false,
                    'pendenciasConclusaoCaptacaoLote' => $pendenciasConclusaoCaptacaoLote ?? [],
                    'concluirCaptacaoUrl' => route('admin.captacao.matriz.concluir-captacao', $lote),
                ])
            </div>
            <ul class="nav nav-tabs flex-wrap" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $aba === 'quantidade' ? 'active' : '' }}"
                       id="matriz-nav-quantidade"
                       data-bs-toggle="tab"
                       data-matriz-aba="quantidade"
                       href="#matriz-tab-quantidade"
                       role="tab"
                       aria-controls="matriz-tab-quantidade"
                       aria-selected="{{ $aba === 'quantidade' ? 'true' : 'false' }}">
                        Captação
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $aba === 'rotas' ? 'active' : '' }}"
                       id="matriz-nav-rotas"
                       data-bs-toggle="tab"
                       data-matriz-aba="rotas"
                       href="#matriz-tab-rotas"
                       role="tab"
                       aria-controls="matriz-tab-rotas"
                       aria-selected="{{ $aba === 'rotas' ? 'true' : 'false' }}">
                        Rotas
                    </a>
                </li>
                @foreach ($gruposOrdemCarregamento as $grupoRota)
                    @php $abaRota = 'rota-'.$grupoRota['id_captacao_rota']; @endphp
                    <li class="nav-item matriz-nav-rota-vinculada" role="presentation" data-rota-id="{{ $grupoRota['id_captacao_rota'] }}">
                        <a class="nav-link {{ $aba === $abaRota ? 'active' : '' }}"
                           id="matriz-nav-{{ $abaRota }}"
                           data-bs-toggle="tab"
                           data-matriz-aba="{{ $abaRota }}"
                           href="#matriz-tab-{{ $abaRota }}"
                           role="tab"
                           aria-controls="matriz-tab-{{ $abaRota }}"
                           aria-selected="{{ $aba === $abaRota ? 'true' : 'false' }}">
                            {{ $grupoRota['rota_nome'] }}
                            @if ($grupoRota['concluida'] ?? false)
                                <span class="badge bg-success-subtle text-success ms-1">✓</span>
                            @endif
                        </a>
                    </li>
                @endforeach
                @if ($lote->status->exibeAbaArquivoCigan())
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $aba === 'arquivo-cigan' ? 'active' : '' }}"
                           id="matriz-nav-arquivo-cigan"
                           data-bs-toggle="tab"
                           data-matriz-aba="arquivo-cigan"
                           href="#matriz-tab-arquivo-cigan"
                           role="tab"
                           aria-controls="matriz-tab-arquivo-cigan"
                           aria-selected="{{ $aba === 'arquivo-cigan' ? 'true' : 'false' }}">
                            @if ($lote->status->exibeAbaArquivoCiganVendas())
                                Arquivo Cigam Venda
                            @else
                                Arquivo Cigam
                            @endif
                        </a>
                    </li>
                @endif
                @if ($lote->status->exibeAbaSaidaEstoqueFisico())
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $aba === 'saida-estoque-fisico' ? 'active' : '' }}"
                           id="matriz-nav-saida-estoque-fisico"
                           data-bs-toggle="tab"
                           data-matriz-aba="saida-estoque-fisico"
                           href="#matriz-tab-saida-estoque-fisico"
                           role="tab"
                           aria-controls="matriz-tab-saida-estoque-fisico"
                           aria-selected="{{ $aba === 'saida-estoque-fisico' ? 'true' : 'false' }}">
                            Saída estoque físico
                        </a>
                    </li>
                @endif
                @if ($lote->status->exibeAbaFreteHub())
                    @can(\App\Enums\Permissions::CAPTACAO_LOTE_FRETE_VINCULAR)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $aba === 'frete-hub' ? 'active' : '' }}"
                               id="matriz-nav-frete-hub"
                               data-bs-toggle="tab"
                               data-matriz-aba="frete-hub"
                               href="#matriz-tab-frete-hub"
                               role="tab"
                               aria-controls="matriz-tab-frete-hub"
                               aria-selected="{{ $aba === 'frete-hub' ? 'true' : 'false' }}">
                                Frete HUB x CD
                            </a>
                        </li>
                    @endcan
                @endif
                @if ($lote->status->exibeAbaFreteVendas())
                    @can(\App\Enums\Permissions::CAPTACAO_LOTE_FRETE_VINCULAR)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $aba === 'frete-vendas' ? 'active' : '' }}"
                               id="matriz-nav-frete-vendas"
                               data-bs-toggle="tab"
                               data-matriz-aba="frete-vendas"
                               href="#matriz-tab-frete-vendas"
                               role="tab"
                               aria-controls="matriz-tab-frete-vendas"
                               aria-selected="{{ $aba === 'frete-vendas' ? 'true' : 'false' }}">
                                Frete Vendas
                            </a>
                        </li>
                    @endcan
                @endif
            </ul>
        </div>
    </div>

    <div class="card captacao-matriz-tabela-card">
        <div class="tab-content">
            <div class="tab-pane fade {{ $aba === 'quantidade' ? 'show active' : '' }}" id="matriz-tab-quantidade" role="tabpanel">
                <div class="card-body captacao-matriz-scroll-wrap pb-3">
                    <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="captacao-matriz">
                <thead>
                <tr id="matriz-header-row">
                    <th class="captacao-matriz-col-loja">Loja</th>
                    @foreach ($frutas as $fruta)
                        <th @class(['captacao-matriz-col-fruta', 'captacao-matriz-col-zebra' => $loop->odd]) data-fruta-id="{{ $fruta->id }}">
                            @include('admin.captacao.matriz._legenda-fruta-vertical', ['nome' => $fruta->nome])
                        </th>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <th class="captacao-matriz-col-saida-fisica text-center">
                            @include('admin.captacao.matriz._legenda-fruta-vertical', ['nome' => 'Saída do estoque físico'])
                        </th>
                        <th class="text-center text-nowrap captacao-matriz-col-conclusao">Conclusão</th>
                    @endif
                </tr>
                </thead>
                <tbody id="matriz-body">
                @foreach ($clientes as $cliente)
                    @php
                        $frutasCliente = $frutasPorCliente[$cliente->id] ?? [];
                        $pedidoLinha = $pedidosPorCliente->get($cliente->id);
                        $linhaConcluida = (bool) $pedidoLinha?->captacao_concluida;
                        $rotaPedidoId = $pedidoLinha?->id_captacao_rota;
                        $rotaPedidoConcluida = $rotaPedidoId
                            ? (bool) ($rotas->firstWhere('id', $rotaPedidoId)?->concluida ?? false)
                            : false;
                        $bloqueiaReabrirCaptacao = $linhaConcluida && $rotaPedidoConcluida;
                        $tituloBtnConclusao = $bloqueiaReabrirCaptacao
                            ? 'A rota desta loja está concluída. Reabra a rota na aba Por rota.'
                            : ($linhaConcluida ? 'Reabrir captação desta loja' : 'Concluir captação desta loja');
                    @endphp
                    <tr class="matriz-row-loja @if($linhaConcluida) matriz-row-loja-concluida @endif" data-cliente-id="{{ $cliente->id }}" data-captacao-concluida="{{ $linhaConcluida ? '1' : '0' }}" data-rota-id="{{ $rotaPedidoId ?? '' }}">
                        <td class="text-nowrap captacao-matriz-col-loja">
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
                            <td class="captacao-matriz-col-saida-fisica">
                                @include('admin.captacao.matriz._celula-saida-fisica-matriz', [
                                    'lote' => $lote,
                                    'cliente' => $cliente,
                                    'pedidoLinha' => $pedidoLinha,
                                    'opcoesSaidaFisicaMatriz' => $opcoesSaidaFisicaMatriz,
                                    'saidaFisicaLoja' => $saidaFisicaLoja,
                                    'linhaConcluida' => $linhaConcluida,
                                ])
                            </td>
                            <td class="text-center align-middle captacao-matriz-col-conclusao">
                                <div class="captacao-matriz-acoes">
                                    @if ($bloqueiaReabrirCaptacao)
                                        <span class="d-inline-block captacao-matriz-concluir-wrap"
                                              title="{{ $tituloBtnConclusao }}"
                                              data-bs-toggle="tooltip"
                                              data-bs-placement="top"
                                              tabindex="0"
                                              role="button"
                                              aria-label="{{ $tituloBtnConclusao }}">
                                    @endif
                                    <button type="button"
                                            class="btn btn-sm btn-matriz-icon {{ $pedidoLinha?->captacao_concluida ? 'btn-success' : 'btn-outline-secondary' }} btn-matriz-concluir"
                                            data-url="{{ route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $cliente]) }}"
                                            data-concluida="{{ $pedidoLinha?->captacao_concluida ? '1' : '0' }}"
                                            @unless($bloqueiaReabrirCaptacao)
                                                title="{{ $tituloBtnConclusao }}"
                                                aria-label="{{ $tituloBtnConclusao }}"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                            @endunless
                                            @disabled($bloqueiaReabrirCaptacao)>
                                        <i class="{{ $pedidoLinha?->captacao_concluida ? 'ri-arrow-go-back-line' : 'ri-check-line' }}" aria-hidden="true"></i>
                                    </button>
                                    @if ($bloqueiaReabrirCaptacao)
                                        </span>
                                    @endif
                                    <button type="button"
                                            class="btn btn-sm btn-matriz-icon btn-outline-danger btn-matriz-remover @if($linhaConcluida) d-none @endif"
                                            data-cliente-id="{{ $cliente->id }}"
                                            data-cliente-nome="{{ $cliente->fantasia ?: $cliente->razao_social }}"
                                            title="Remover loja da captação"
                                            aria-label="Remover loja da captação"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top">
                                        <i class="ri-delete-bin-line" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach

                @if ($clientes->isNotEmpty())
                <tr id="matriz-row-totais">
                    <td class="text-nowrap captacao-matriz-col-loja">Total</td>
                    @foreach ($frutas as $fruta)
                        <td class="matriz-total-celula" data-fruta-id="{{ $fruta->id }}">
                            @php $totalFruta = (float) ($totaisPorFruta[$fruta->id] ?? 0); @endphp
                            {{ $totalFruta > 0 ? (int) $totalFruta : '' }}
                        </td>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <td></td>
                        <td></td>
                    @endif
                </tr>
                @endif

                <tr id="matriz-row-adicionar">
                    <td class="captacao-matriz-col-loja">
                        <div class="d-flex flex-column gap-1">
                            <select id="select-nova-loja"
                                    class="form-select form-select-sm"
                                    data-search-select
                                    data-placeholder="Adicionar loja"
                                    @disabled($clientesDisponiveis->isEmpty())>
                                <option value="">Adicionar loja…</option>
                                @foreach ($clientesDisponiveis as $disponivel)
                                    <option value="{{ $disponivel->id }}">{{ $disponivel->fantasia ?: $disponivel->razao_social }}</option>
                                @endforeach
                            </select>
                        </div>
                    </td>
                    @foreach ($frutas as $fruta)
                        <td @class(['captacao-matriz-celula-bloqueada', 'captacao-matriz-col-zebra' => $loop->odd])></td>
                    @endforeach
                    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                        <td></td>
                        <td></td>
                    @endif
                </tr>
                </tbody>
            </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade {{ $aba === 'rotas' ? 'show active' : '' }}" id="matriz-tab-rotas" role="tabpanel">
                <div class="card-body table-responsive pt-3 pb-3">
                    <p class="text-muted small mb-2">
                        Itens com quantidade captada na aba Captação. A rota é vinculada por loja (mesma rota para todos os itens da loja).
                        A rota é salva automaticamente ao selecionar. A ordem de carregamento é definida na aba
                        <strong>da rota</strong>. O lote só avança após clicar em
                        <strong>Concluir rotas e carregamento</strong> (com tudo preenchido).
                        @if ($lote->carteira)
                            <span class="d-block mt-1">Carteira do lote: <strong>{{ $lote->carteira->nome }}</strong> — só aparecem rotas cadastradas nesta carteira.</span>
                        @endif
                    </p>
                    @if ($rotas->isEmpty())
                        <div id="matriz-rotas-sem-cadastro" class="alert alert-warning py-2 small mb-3">
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

            @foreach ($gruposOrdemCarregamento as $grupoRota)
                @include('admin.captacao.matriz._ordem-carregamento-rota-pane', [
                    'grupoRota' => $grupoRota,
                    'aba' => $aba,
                    'lote' => $lote,
                    'veiculos' => $veiculos,
                    'rotas' => $rotas,
                ])
            @endforeach

            @if ($lote->status->exibeAbaArquivoCigan())
                <div class="tab-pane fade {{ $aba === 'arquivo-cigan' ? 'show active' : '' }}" id="matriz-tab-arquivo-cigan" role="tabpanel">
                    <div class="card-body pt-4 pb-4">
                        @if ($lote->status->exibeAbaArquivoCiganVendas())
                            @include('admin.captacao.matriz._arquivo-cigan-vendas', [
                                'lote' => $lote,
                                'resumoVendasLote' => $resumoVendasLote ?? [],
                            ])
                        @endif

                        @if ($lote->status->exibeAbaArquivoCiganTransferencia())
                        <div class="border rounded p-3 mb-3">
                            <h6 class="mb-1">Transferência — arquivo para o Cigam</h6>
                            <p class="text-muted small mb-3 mb-md-3">
                                TXT com as quantidades <strong>a receber</strong> no galpão. Selecione o HUB de origem, salve e baixe o arquivo para importar no Cigam.
                            </p>

                            @can(\App\Enums\Permissions::CAPTACAO_LOTE_TRANSFERENCIA_INICIAR)
                                <div class="row g-2 align-items-end">
                                    <div class="col-md">
                                        <form method="post" action="{{ route('admin.captacao.lotes.hub-origem-cigan.update', $lote) }}">
                                            @csrf
                                            @method('PUT')
                                            <label class="form-label" for="hub-origem-cigan">Unidade HUB de origem</label>
                                            <div class="d-flex flex-column flex-sm-row gap-2">
                                                <select name="id_unidade_negocio_hub_origem"
                                                        id="hub-origem-cigan"
                                                        class="form-select form-select-sm flex-grow-1 @error('id_unidade_negocio_hub_origem') is-invalid @enderror"
                                                        data-search-select
                                                        data-placeholder="Selecione ou pesquise o HUB"
                                                        required>
                                                    <option value="">Selecione o HUB…</option>
                                                    @foreach ($hubsDisponiveis as $hub)
                                                        <option value="{{ $hub->id }}" @selected((int) old('id_unidade_negocio_hub_origem', $lote->id_unidade_negocio_hub_origem) === $hub->id)>
                                                            {{ $hub->nome }} (Cigam {{ $hub->id_cigam }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-soft-primary btn-sm flex-shrink-0">
                                                    Salvar HUB
                                                </button>
                                            </div>
                                            @error('id_unidade_negocio_hub_origem')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </form>
                                    </div>
                                    <div class="col-md-auto">
                                        <label class="form-label d-md-none" for="btn-download-cigan-txt">Download</label>
                                        <label class="form-label d-none d-md-block invisible user-select-none mb-2" aria-hidden="true">&nbsp;</label>
                                        @if ($lote->id_unidade_negocio_hub_origem)
                                            <a id="btn-download-cigan-txt"
                                               href="{{ route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote) }}"
                                               class="btn btn-primary btn-sm d-block d-md-inline-block text-nowrap">
                                                <i class="ri-download-2-line me-1"></i> Baixar arquivo Cigam
                                            </a>
                                        @else
                                            <button id="btn-download-cigan-txt"
                                                    type="button"
                                                    class="btn btn-primary btn-sm d-block d-md-inline-block text-nowrap"
                                                    disabled
                                                    title="Selecione e salve o HUB de origem">
                                                <i class="ri-download-2-line me-1"></i> Baixar arquivo Cigam
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @if ($lote->unidadeHubOrigem)
                                    <p class="small mb-0 mt-2 text-success">
                                        HUB salvo: <strong>{{ $lote->unidadeHubOrigem->nome }}</strong>
                                        (Cigam {{ $lote->unidadeHubOrigem->id_cigam }})
                                    </p>
                                @elseif (! $lote->id_unidade_negocio_hub_origem)
                                    <p class="small text-muted mb-0 mt-2">Salve o HUB de origem para habilitar o download.</p>
                                @endif
                            @else
                                <p class="small text-warning mb-0">Sem permissão para configurar o HUB ou baixar o arquivo.</p>
                            @endcan
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light-subtle">
                            <h6 class="mb-2">NF de transferência</h6>
                            <p class="text-muted small mb-3">
                                Após importar o TXT no Cigam, envie aqui a NF gerada (XML, PDF ou TXT). O sistema registra as
                                <strong>transferências</strong> do HUB salvo para o galpão do lote e avança para
                                <strong>Aguardando vínculo de frete</strong>.
                            </p>
                            @if ($lote->possuiNfTransferencia())
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    @can(\App\Enums\Permissions::CAPTACAO_LOTE_TRANSFERENCIA_VALIDAR)
                                        <a href="{{ route('admin.captacao.lotes.nf-transferencia-cigan.download', $lote) }}"
                                           class="btn btn-soft-success btn-sm">
                                            <i class="ri-file-download-line me-1"></i>
                                            Baixar NF enviada
                                            @if ($lote->arquivo_nf_transferencia_nome)
                                                ({{ $lote->arquivo_nf_transferencia_nome }})
                                            @endif
                                        </a>
                                    @endcan
                                    @if ($lote->nf_transferencia_enviada_em)
                                        <span class="small text-muted">
                                            Enviada em {{ $lote->nf_transferencia_enviada_em->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                            @if ($lote->status->permiteUploadNfTransferenciaCigan())
                                @can(\App\Enums\Permissions::CAPTACAO_LOTE_TRANSFERENCIA_VALIDAR)
                                    <form method="post"
                                          action="{{ route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote) }}"
                                          enctype="multipart/form-data"
                                          class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-8">
                                            <label class="form-label" for="arquivo-nf-transferencia">Arquivo da NF</label>
                                            <input type="file"
                                                   name="arquivo_nf_transferencia"
                                                   id="arquivo-nf-transferencia"
                                                   class="form-control form-control-sm @error('arquivo_nf_transferencia') is-invalid @enderror"
                                                   accept=".xml,.pdf,.txt,application/xml,text/xml,application/pdf,text/plain"
                                                   required>
                                            @error('arquivo_nf_transferencia')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error('status')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-success btn-sm w-100">
                                                <i class="ri-upload-2-line me-1"></i> Enviar NF e avançar
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <p class="small text-warning mb-0">Sem permissão para enviar a NF de transferência.</p>
                                @endcan
                            @elseif ($lote->status === \App\Enums\CaptacaoLoteStatus::AguardandoVinculoFrete)
                                <p class="small text-success mb-0">
                                    Etapa concluída. Use o botão acima para baixar a NF ou prossiga com o vínculo de frete na aba <strong>Frete</strong>.
                                </p>
                            @endif
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
                                        <td colspan="3" class="text-muted">Sem quantidades a receber no galpão.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mt-2 mb-0">
                            Apenas frutas com quantidade a receber &gt; 0 entram no arquivo.
                        </p>
                        @endif
                    </div>
                </div>
            @endif

            @if ($lote->status->exibeAbaSaidaEstoqueFisico())
                <div class="tab-pane fade {{ $aba === 'saida-estoque-fisico' ? 'show active' : '' }}" id="matriz-tab-saida-estoque-fisico" role="tabpanel">
                    <div class="card-body pt-3 pb-3">
                        <p class="text-muted small">
                            Romaneio de carregamento por rota, na ordem definida na aba da rota correspondente.
                            Escolha de onde debitar o estoque na <strong>venda</strong> (padrão: galpão).
                            Ao concluir a etapa, o SB efetiva as transferências HUB → galpão.
                        </p>
                        @include('admin.captacao._romaneio-carregamento-por-rota', [
                            'romaneiosPorRota' => $romaneiosCarregamentoPorRota,
                            'lote' => $lote,
                            'pedidosPorCliente' => $pedidosPorCliente,
                            'variante' => 'saida-fisico',
                            'idPrefixo' => 'matriz-romaneio',
                        ])
                    </div>
                </div>
            @endif

            @if ($dadosFreteHub !== null)
                @can(\App\Enums\Permissions::CAPTACAO_LOTE_FRETE_VINCULAR)
                    <div class="tab-pane fade {{ $aba === 'frete-hub' ? 'show active' : '' }}" id="matriz-tab-frete-hub" role="tabpanel">
                        @include('admin.captacao.matriz._frete-hub-lote', [
                            'lote' => $lote,
                            'transferencias' => $dadosFreteHub['transferencias'],
                            'fretesAbertos' => $dadosFreteHub['fretesAbertos'],
                        ])
                    </div>
                @endcan
            @endif
            @if ($dadosFreteVendas !== null)
                @can(\App\Enums\Permissions::CAPTACAO_LOTE_FRETE_VINCULAR)
                    <div class="tab-pane fade {{ $aba === 'frete-vendas' ? 'show active' : '' }}" id="matriz-tab-frete-vendas" role="tabpanel">
                        @include('admin.captacao.matriz._frete-vendas-lote', [
                            'lote' => $lote,
                            'lojas' => $dadosFreteVendas['lojas'],
                            'fretesAbertos' => $dadosFreteVendas['fretesAbertos'],
                            'freteVendaEditavel' => $freteVendaEditavel ?? false,
                        ])
                    </div>
                @endcan
            @endif
        </div>
    </div>
    </div>

    @php
        $nfEstoqueHubFaltas = session('nf_transferencia_estoque_hub_insuficiente');
    @endphp
    @if (is_array($nfEstoqueHubFaltas) && ! empty($nfEstoqueHubFaltas['frutas']))
        @include('admin.captacao.matriz._modal-nf-estoque-hub-insuficiente', [
            'nfEstoqueHubFaltas' => $nfEstoqueHubFaltas,
        ])
    @endif

@endsection

@include('admin.captacao._search-select-scripts')

@push('scripts')
@if (is_array($nfEstoqueHubFaltas ?? null) && ! empty($nfEstoqueHubFaltas['frutas']))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modal-nf-estoque-hub-insuficiente');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
</script>
@endif
@php
    $rotasOptionsInicial = $rotas->map(function ($r) {
        return [
            'id' => $r->id,
            'nome' => $r->nome,
            'id_veiculo' => $r->id_veiculo,
            'concluida' => (bool) ($r->concluida ?? false),
        ];
    })->values();

    $opcoesSaidaFisicaMatrizJson = collect($opcoesSaidaFisicaMatriz ?? [])->map(fn (array $o) => [
        'id' => $o['id'],
        'label' => $o['label_curto'],
        'title' => $o['label'],
    ])->values();
@endphp
<script>
(function () {
    const urlCelula = @json(route('admin.captacao.lotes.celula.update', $lote));
    const urlEstado = @json(route('admin.captacao.lotes.matriz.estado', $lote));
    const urlAdicionarLoja = @json(route('admin.captacao.lotes.matriz.adicionar-loja', $lote));
    const urlRemoverLoja = @json(route('admin.captacao.lotes.matriz.remover-loja', $lote));
    const urlPedidoBase = @json(url('admin/captacao/lotes/'.$lote->id.'/pedidos'));
    const urlRotasCadastro = @json($urlRotasCadastro);
    let rotasOptions = @json($rotasOptionsInicial);
    let veiculosOptions = @json($veiculos->map(fn ($v) => ['id' => $v->id, 'id_sbs' => $v->id_sbs, 'nome' => $v->nome])->values());
    const loteId = @json($lote->id);
    let rotasPendentesConclusaoCaptacao = @json($rotasPendentesConclusaoCaptacao ?? []);
    let todasRotasDoLoteConcluidas = @json($todasRotasDoLoteConcluidas ?? true);
    const urlMatrizPorRota = @json(route('admin.captacao.matriz.index', ['lote' => $lote->id]));
    const idUnidadeGalpaoSaida = @json((int) $lote->id_unidade_negocio_galpao);
    const idUnidadeHubSaida = @json($lote->id_unidade_negocio_hub_origem);
    const nomeUnidadeGalpaoSaida = @json($lote->unidadeGalpao?->nome ?? 'Galpão');
    const nomeUnidadeHubSaida = @json($lote->unidadeHubOrigem?->nome);
    const opcoesSaidaFisicaMatriz = @json($opcoesSaidaFisicaMatrizJson);
    const emCaptacao = @json($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento);
    const permiteEdicaoQuantidade = @json($lote->status->permiteEdicaoQuantidadeCaptacao());
    const permiteEdicaoPreco = @json($lote->status->permiteEdicaoPreco());
    const permiteVinculoRota = @json($lote->status->permiteEdicaoVinculoRota());
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const badge = document.getElementById('matriz-sync-badge');
    const selectLoja = document.getElementById('select-nova-loja');
    let matrizVersion = null;
    let adicionandoLoja = false;
    let removendoLoja = false;
    const salvandoSaidaFisica = new Set();
    let pollTimer = null;

    function initCaptacaoSearchSelects(root) {
        if (typeof window.AdminSearchSelect?.init === 'function') {
            window.AdminSearchSelect.init(root || document);
        }
    }

    function destroyCaptacaoSearchSelect(selectEl) {
        if (!selectEl || !window.jQuery?.fn?.select2) {
            return;
        }

        const $select = window.jQuery(selectEl);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }
    let layoutHash = @json($layoutHash);
    let salvando = false;
    let ignorarOrdemChange = false;
    const ordemSaveTimers = new Map();

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
        if (data.errors?.rota) {
            return extrairPendenciasMensagem(data.errors.rota);
        }
        if (data.message) {
            return data.message;
        }
        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }

        return 'Não foi possível concluir a operação.';
    }

    function extrairPendenciasMensagem(rotaErrors) {
        const itens = Array.isArray(rotaErrors) ? rotaErrors : [rotaErrors];

        return itens.filter(Boolean).join(' ');
    }

    function pendenciasDoBotao(btn) {
        const bruto = btn?.dataset?.pendencias;
        if (!bruto) {
            return [];
        }

        try {
            const parsed = JSON.parse(bruto);
            return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
        } catch (e) {
            return [];
        }
    }

    function formatarPendenciasTexto(pendencias) {
        const itens = (pendencias || []).filter(Boolean);
        if (itens.length === 0) {
            return 'Complete motorista, veículo, ordem de carregamento e captação de todas as lojas para concluir a rota.';
        }
        if (itens.length === 1) {
            return itens[0];
        }

        return `Para concluir a rota:\n${itens.map((item) => `• ${item}`).join('\n')}`;
    }

    function mensagemAmigavelErroConcluirRota(data, status) {
        const pendencias = data?.errors?.rota
            ? (Array.isArray(data.errors.rota) ? data.errors.rota : [data.errors.rota])
            : [];
        if (pendencias.length > 0) {
            return formatarPendenciasTexto(pendencias);
        }

        const bruto = String(data?.message || '').trim();
        if (bruto !== '' && !/SQLSTATE|SQL:|Duplicate entry|Unknown column|Integrity constraint/i.test(bruto)) {
            return bruto;
        }

        if (status === 422) {
            return 'Não foi possível concluir a rota. Verifique motorista, veículo, sequência de carregamento e captação de todas as lojas.';
        }

        return 'Não foi possível concluir a rota. Tente novamente ou atualize a página.';
    }

    function matrizDismissToast(toastEl) {
        if (!toastEl || toastEl.dataset.dismissing === '1') {
            return;
        }
        toastEl.dataset.dismissing = '1';
        toastEl.classList.remove('is-visible');
        toastEl.classList.add('is-hiding');
        window.setTimeout(() => toastEl.remove(), 280);
    }

    function matrizShowToast(mensagem, variant = 'warning', duracaoMs = 8000) {
        const texto = String(mensagem || '').trim();
        if (texto === '') {
            return;
        }

        if (typeof window.AdminDataTable?.showToast === 'function') {
            window.AdminDataTable.showToast(texto, variant, duracaoMs);
            return;
        }

        const hostId = 'admin-datatable-toast-host';
        let host = document.getElementById(hostId);
        if (!host) {
            host = document.createElement('div');
            host.id = hostId;
            host.className = 'admin-datatable-toast-host';
            host.setAttribute('aria-live', 'polite');
            host.setAttribute('aria-atomic', 'true');
            document.body.appendChild(host);
        }

        host.querySelectorAll('.admin-datatable-toast').forEach((el) => matrizDismissToast(el));

        const icons = {
            success: 'ri-checkbox-circle-line',
            danger: 'ri-error-warning-line',
            warning: 'ri-alert-line',
            info: 'ri-information-line',
        };

        const toast = document.createElement('div');
        toast.className = `admin-datatable-toast admin-datatable-toast--${variant}`;
        toast.innerHTML = `
            <div class="admin-datatable-toast__content">
                <i class="${icons[variant] || icons.warning}" aria-hidden="true"></i>
                <span class="admin-datatable-toast__message"></span>
                <button type="button" class="admin-datatable-toast__close" aria-label="Fechar">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </div>
        `;

        toast.querySelector('.admin-datatable-toast__message').textContent = texto;
        toast.querySelector('.admin-datatable-toast__close').addEventListener('click', () => matrizDismissToast(toast));

        host.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        window.setTimeout(() => matrizDismissToast(toast), duracaoMs);
    }

    async function buscarPendenciasConclusaoRota(btn) {
        try {
            const estadoRes = await fetch(urlEstado, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!estadoRes.ok) {
                return null;
            }

            const data = await estadoRes.json();
            const rotaId = Number(btn.dataset.rota);
            const grupo = (data.grupos_ordem_carregamento || []).find(
                (item) => Number(item.id_captacao_rota) === rotaId,
            );

            if (!grupo) {
                return ['Rota não encontrada. Atualize a página.'];
            }

            if (grupo.pode_concluir) {
                return [];
            }

            return Array.isArray(grupo.pendencias_conclusao) ? grupo.pendencias_conclusao.filter(Boolean) : [];
        } catch (e) {
            return null;
        }
    }

    function formatarPendenciasTooltipHtml(pendencias) {
        const itens = (pendencias || []).filter(Boolean);
        if (itens.length === 0) {
            return 'Informe motorista, veículo e ordem de carregamento.';
        }

        if (itens.length === 1) {
            return escHtml(itens[0]);
        }

        return `<div class="text-start"><strong>Para concluir a rota:</strong><ul class="mb-0 ps-3 mt-1">${itens.map((item) => `<li>${escHtml(item)}</li>`).join('')}</ul></div>`;
    }

    function mostrarTooltipTemporario(el, htmlOuTexto, duracaoMs = 5000) {
        if (!el) {
            return;
        }

        if (!window.bootstrap?.Tooltip) {
            mostrarErro(extrairPendenciasMensagem(Array.isArray(htmlOuTexto) ? htmlOuTexto : [htmlOuTexto]));
            return;
        }

        const existente = bootstrap.Tooltip.getInstance(el);
        if (existente) {
            existente.dispose();
        }

        el.setAttribute('data-bs-html', 'true');
        el.setAttribute('data-bs-custom-class', 'captacao-matriz-tooltip-pendencias');
        el.setAttribute('title', htmlOuTexto);

        const tooltip = new bootstrap.Tooltip(el, {
            trigger: 'manual',
            html: true,
            placement: 'bottom',
            customClass: 'captacao-matriz-tooltip-pendencias',
        });

        tooltip.show();

        window.setTimeout(() => {
            tooltip.hide();
            tooltip.dispose();
            el.removeAttribute('data-bs-html');
            el.removeAttribute('data-bs-custom-class');
            initMatrizTooltips(el.closest('.matriz-tab-pane-rota-vinculada') || el.parentElement || document);
        }, duracaoMs);
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

    function initMatrizTooltips(root) {
        if (!window.bootstrap?.Tooltip) {
            return;
        }

        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            const existente = bootstrap.Tooltip.getInstance(el);
            if (existente) {
                existente.dispose();
            }
            new bootstrap.Tooltip(el);
        });
    }

    function atualizarLegendaBtnIcone(btn, texto) {
        if (!btn) {
            return;
        }

        btn.setAttribute('aria-label', texto);

        const tooltip = window.bootstrap?.Tooltip?.getInstance(btn);
        if (tooltip) {
            tooltip.hide();
            tooltip.dispose();
        }

        // Bootstrap move o title para data-bs-original-title na primeira init.
        btn.setAttribute('title', texto);
        btn.setAttribute('data-bs-original-title', texto);
        btn.setAttribute('data-bs-title', texto);

        if (window.bootstrap?.Tooltip) {
            new bootstrap.Tooltip(btn);
        }
    }

    function pedidoRotaConcluida(rotaId) {
        if (!rotaId) {
            return false;
        }

        const rota = rotasOptions.find((item) => Number(item.id) === Number(rotaId));

        return Boolean(rota?.concluida);
    }

    function rotaIdDoPedido(clienteId, pedido = null) {
        if (pedido?.id_captacao_rota) {
            return Number(pedido.id_captacao_rota);
        }

        const select = document.querySelector(`.matriz-rota-select[data-cliente="${clienteId}"]`);

        return select?.value ? Number(select.value) : null;
    }

    function tituloBtnConcluirLoja(concluida, rotaId) {
        if (concluida && pedidoRotaConcluida(rotaId)) {
            return 'A rota desta loja está concluída. Reabra a rota na aba Por rota.';
        }

        return concluida ? 'Reabrir captação desta loja' : 'Concluir captação desta loja';
    }

    function htmlBtnConcluirLoja(concluida, url, rotaId = null) {
        const titulo = tituloBtnConcluirLoja(concluida, rotaId);
        const btnClass = concluida ? 'btn-success' : 'btn-outline-secondary';
        const icon = concluida ? 'ri-arrow-go-back-line' : 'ri-check-line';
        const bloqueado = concluida && pedidoRotaConcluida(rotaId);
        const disabled = bloqueado ? ' disabled' : '';
        const btn = `<button type="button" class="btn btn-sm btn-matriz-icon ${btnClass} btn-matriz-concluir"${disabled} data-url="${url}" data-concluida="${concluida ? '1' : '0'}"${bloqueado ? '' : ` title="${escHtml(titulo)}" aria-label="${escHtml(titulo)}" data-bs-toggle="tooltip" data-bs-placement="top"`}><i class="${icon}" aria-hidden="true"></i></button>`;

        if (! bloqueado) {
            return btn;
        }

        return `<span class="d-inline-block captacao-matriz-concluir-wrap" title="${escHtml(titulo)}" data-bs-toggle="tooltip" data-bs-placement="top" tabindex="0" role="button" aria-label="${escHtml(titulo)}">${btn}</span>`;
    }

    function atualizarAlertaRotasPendentesCaptacao() {
        const alerta = document.getElementById('matriz-alert-rotas-pendentes');
        if (! alerta) {
            return;
        }

        if (rotasPendentesConclusaoCaptacao.length === 0) {
            alerta.classList.add('d-none');
            return;
        }

        alerta.classList.remove('d-none');
        const rotasHtml = rotasPendentesConclusaoCaptacao.map((nome) => `<strong>${escHtml(nome)}</strong>`).join(', ');
        alerta.innerHTML = `<strong>Conclusão da loja bloqueada.</strong> Conclua todas as rotas com pedido nas abas de rota antes de finalizar a captação de cada loja.<span class="d-block mt-1">Rotas pendentes: ${rotasHtml}</span>`;
    }

    function duasLinhasLegendaFruta(nome) {
        const texto = String(nome ?? '').trim();
        if (texto === '') {
            return ['', ''];
        }

        const partes = texto.split(/\s+/).filter(Boolean);
        if (partes.length >= 2) {
            const meio = Math.ceil(partes.length / 2);

            return [
                partes.slice(0, meio).join(' '),
                partes.slice(meio).join(' '),
            ];
        }

        if (texto.length <= 10) {
            return [texto, ''];
        }

        const meio = Math.ceil(texto.length / 2);

        return [texto.slice(0, meio), texto.slice(meio)];
    }

    function htmlLegendaFrutaMatriz(nome) {
        const [linha1, linha2] = duasLinhasLegendaFruta(nome);
        let html = `<span class="captacao-matriz-fruta-nome" title="${escHtml(nome)}">`;

        [linha1, linha2].filter((linha) => linha !== '').forEach((linha) => {
            html += `<span class="captacao-matriz-fruta-linha">${escHtml(linha)}</span>`;
        });

        html += '</span>';

        return html;
    }

    function htmlBtnRemoverLoja(clienteId, nome, oculto) {
        const titulo = 'Remover loja da captação';

        return `<button type="button" class="btn btn-sm btn-matriz-icon btn-outline-danger btn-matriz-remover${oculto ? ' d-none' : ''}" data-cliente-id="${clienteId}" data-cliente-nome="${escHtml(nome)}" title="${titulo}" aria-label="${titulo}" data-bs-toggle="tooltip" data-bs-placement="top"><i class="ri-delete-bin-line" aria-hidden="true"></i></button>`;
    }

    function bindConcluirButtons(root) {
        (root || document).querySelectorAll('.captacao-matriz-concluir-wrap:not([data-bound-concluir-wrap])').forEach((wrap) => {
            wrap.dataset.boundConcluirWrap = '1';
            wrap.addEventListener('click', () => {
                const btn = wrap.querySelector('.btn-matriz-concluir');
                if (! btn?.disabled) {
                    return;
                }

                const titulo = wrap.getAttribute('title') || wrap.getAttribute('aria-label') || '';
                if (typeof matrizShowToast === 'function') {
                    matrizShowToast(titulo, 'warning');
                } else if (titulo) {
                    mostrarErro(titulo);
                }
            });
        });

        (root || document).querySelectorAll('.btn-matriz-concluir:not([data-bound-concluir])').forEach((btn) => {
            btn.dataset.boundConcluir = '1';
            btn.addEventListener('click', async function () {
                if (this.disabled) {
                    return;
                }

                const novaConclusao = this.dataset.concluida === '1' ? '0' : '1';
                const row = this.closest('tr.matriz-row-loja');
                const clienteId = row?.dataset.clienteId;

                if (novaConclusao === '0' && clienteId && pedidoRotaConcluida(rotaIdDoPedido(clienteId))) {
                    mostrarErro('A rota desta loja está concluída. Reabra a rota na aba Por rota para reabrir a captação.');
                    return;
                }

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
                    await sincronizarGruposOrdemCarregamento();
                    if (typeof window.syncConclusaoCaptacaoLote === 'function') {
                        await window.syncConclusaoCaptacaoLote();
                    } else {
                        await pollEstado();
                    }
                    setBadge('sincronizado');
                } catch (e) {
                    mostrarErro('Erro ao atualizar conclusão.');
                    setBadge('erro');
                }
            });
        });
    }

    bindConcluirButtons();
    initMatrizTooltips(document.getElementById('captacao-matriz'));

    function frutasDoCliente(frutasPorCliente, clienteId) {
        return frutasPorCliente?.[clienteId]
            || frutasPorCliente?.[String(clienteId)]
            || [];
    }

    function clienteTemFruta(frutasPorCliente, clienteId, frutaId) {
        return frutasDoCliente(frutasPorCliente, clienteId)
            .some((id) => Number(id) === Number(frutaId));
    }

    function buildSaidaFisicaColunaHtml(clienteId, pedido, concluida) {
        const saidaAtual = pedido.id_unidade_negocio_saida_venda !== null && pedido.id_unidade_negocio_saida_venda !== undefined
            ? String(pedido.id_unidade_negocio_saida_venda)
            : String(idUnidadeGalpaoSaida);

        let html = '<td class="captacao-matriz-col-saida-fisica">';
        html += `<div class="captacao-matriz-saida-opcoes" data-saida-fisica-loja="${clienteId}">`;

        (opcoesSaidaFisicaMatriz || []).forEach((opcao) => {
            const checked = String(opcao.id) === saidaAtual ? ' checked' : '';
            const disabled = concluida ? ' disabled' : '';
            html += '<label class="captacao-matriz-saida-opcao mb-0">';
            html += `<input type="radio" class="form-check-input captacao-saida-fisica-radio" name="saida_fisica_${clienteId}" value="${opcao.id}" data-cliente="${clienteId}" data-url="${urlPedidoBase}/${clienteId}/saida-fisica-venda"${checked}${disabled}>`;
            html += `<span title="${escHtml(opcao.title || opcao.label)}">${escHtml(opcao.label)}</span></label>`;
        });

        html += '</div></td>';
        return html;
    }

    function buildLinhaLojaHtml(cliente, frutas, frutasPorCliente, data) {
        const pedido = data.pedidos?.[String(cliente.id)] || {};
        const concluida = !!pedido.captacao_concluida;
        const rowClass = concluida ? ' matriz-row-loja-concluida' : '';

        let html = `<tr class="matriz-row-loja${rowClass}" data-cliente-id="${cliente.id}" data-captacao-concluida="${concluida ? '1' : '0'}">`;
        html += '<td class="text-nowrap captacao-matriz-col-loja">';
        html += `<p class="mb-0 captacao-matriz-loja-nome fw-semibold">${escHtml(cliente.nome)}</p>`;
        if (emCaptacao) {
            html += `<input type="text" class="form-control form-control-sm captacao-numero-pedido" maxlength="60" placeholder="Nº pedido" data-cliente="${cliente.id}" data-url="${urlPedidoBase}/${cliente.id}/numero-pedido" value="${escHtml(pedido.numero_pedido ?? '')}"${concluida ? ' disabled' : ''}>`;
        } else if (pedido.numero_pedido) {
            html += `<span class="text-muted small d-block mt-1">Pedido ${escHtml(pedido.numero_pedido)}</span>`;
        }
        html += '</td>';

        frutas.forEach((fruta, idx) => {
            const zebra = idx % 2 === 1 ? ' captacao-matriz-col-zebra' : '';
            const temVinculo = clienteTemFruta(frutasPorCliente, cliente.id, fruta.id);
            const cel = data.celulas?.[`${cliente.id}_${fruta.id}`] || {};
            const podeEditarQty = temVinculo && !concluida && permiteEdicaoQuantidade;
            const podeEditarPreco = temVinculo && permiteEdicaoPreco;
            const bloqueada = !temVinculo || (concluida && !permiteEdicaoPreco);

            html += `<td class="${bloqueada ? 'captacao-matriz-celula-bloqueada' : ''}${zebra}">`;
            if (temVinculo) {
                const precoDigitos = cel.preco_venda && parseFloat(cel.preco_venda) > 0
                    ? String(Math.round(parseFloat(cel.preco_venda) * 100))
                    : '';
                const precoExib = precoDigitos ? formatarPrecoBr(precoDigitos) : '';
                const qtyVal = cel.quantidade && parseFloat(cel.quantidade) > 0
                    ? String(parseInt(cel.quantidade, 10))
                    : '';

                html += '<div class="captacao-matriz-celula-stack">';
                html += `<input type="number" class="form-control form-control-sm captacao-celula captacao-celula-qty" step="1" min="0" data-lote="${loteId}" data-cliente="${cliente.id}" data-fruta="${fruta.id}" data-version="${cel.version ?? ''}" value="${qtyVal}" title="Quantidade"${podeEditarQty ? '' : ' disabled readonly'}>`;
                html += `<input type="text" class="form-control form-control-sm captacao-celula captacao-celula-preco" inputmode="numeric" autocomplete="off" placeholder="0,00" data-lote="${loteId}" data-cliente="${cliente.id}" data-fruta="${fruta.id}" data-version="${cel.version ?? ''}" data-raw-digitos="${precoDigitos}" value="${escHtml(precoExib)}" title="Preço (R$)"${podeEditarPreco ? '' : ' disabled readonly'}>`;
                html += '</div>';
            } else {
                html += '<span class="captacao-matriz-sem-vinculo" title="Fruta não vinculada a esta loja" aria-label="Sem vínculo">×</span>';
            }
            html += '</td>';
        });

        if (emCaptacao) {
            html += buildSaidaFisicaColunaHtml(cliente.id, pedido, concluida);
            html += '<td class="text-center align-middle captacao-matriz-col-conclusao"><div class="captacao-matriz-acoes">';
            html += htmlBtnConcluirLoja(concluida, `${urlPedidoBase}/${cliente.id}/captacao-concluida`, rotaIdDoPedido(cliente.id, pedido));
            html += htmlBtnRemoverLoja(cliente.id, cliente.nome, concluida);
            html += '</div></td>';
        }

        html += '</tr>';
        return html;
    }

    function atualizarSelectLojasDisponiveis(clientes) {
        if (!selectLoja) {
            return;
        }

        destroyCaptacaoSearchSelect(selectLoja);
        selectLoja.innerHTML = '<option value="">Adicionar loja…</option>';
        (clientes || []).forEach((cliente) => {
            const opt = document.createElement('option');
            opt.value = String(cliente.id);
            opt.textContent = cliente.nome;
            selectLoja.appendChild(opt);
        });
        selectLoja.disabled = (clientes || []).length === 0;
        initCaptacaoSearchSelects(selectLoja.closest('td') || document);
        bindSelectNovaLoja();
    }

    function atualizarLinhaAdicionar(frutas) {
        const row = document.getElementById('matriz-row-adicionar');
        if (!row) {
            return;
        }

        const celulaSelect = row.querySelector('td');
        row.querySelectorAll('td').forEach((td) => {
            if (td !== celulaSelect) {
                td.remove();
            }
        });

        frutas.forEach((fruta, idx) => {
            const td = document.createElement('td');
            td.classList.add('captacao-matriz-celula-bloqueada');
            if (idx % 2 === 1) {
                td.classList.add('captacao-matriz-col-zebra');
            }
            row.appendChild(td);
        });

        if (emCaptacao) {
            row.appendChild(document.createElement('td'));
            row.appendChild(document.createElement('td'));
        }
    }

    function reconstruirMatrizCaptacao(data) {
        if (!data?.frutas) {
            return;
        }

        layoutHash = data.layout_hash ?? layoutHash;

        const headerRow = document.getElementById('matriz-header-row');
        if (headerRow) {
            let headerHtml = '<th class="captacao-matriz-col-loja">Loja</th>';
            data.frutas.forEach((fruta, idx) => {
                const zebra = idx % 2 === 1 ? ' captacao-matriz-col-zebra' : '';
                headerHtml += `<th class="captacao-matriz-col-fruta${zebra}" data-fruta-id="${fruta.id}">`;
                headerHtml += `${htmlLegendaFrutaMatriz(fruta.nome)}</th>`;
            });
            if (emCaptacao) {
                headerHtml += '<th class="captacao-matriz-col-saida-fisica text-center">';
                headerHtml += `${htmlLegendaFrutaMatriz('Saída do estoque físico')}</th>`;
                headerHtml += '<th class="text-center text-nowrap captacao-matriz-col-conclusao">Conclusão</th>';
            }
            headerRow.innerHTML = headerHtml;
        }

        const tbody = document.getElementById('matriz-body');
        const rowAdicionar = document.getElementById('matriz-row-adicionar');
        if (!tbody || !rowAdicionar) {
            return;
        }

        tbody.querySelectorAll('tr.matriz-row-loja, #matriz-row-totais').forEach((tr) => tr.remove());

        const frutasPorCliente = data.frutas_por_cliente || {};
        const fragment = document.createDocumentFragment();

        (data.clientes || []).forEach((cliente) => {
            const wrap = document.createElement('tbody');
            wrap.innerHTML = buildLinhaLojaHtml(cliente, data.frutas, frutasPorCliente, data);
            fragment.appendChild(wrap.firstElementChild);
        });

        if ((data.clientes || []).length > 0) {
            const trTotais = document.createElement('tr');
            trTotais.id = 'matriz-row-totais';
            let totaisHtml = '<td class="text-nowrap captacao-matriz-col-loja">Total</td>';
            data.frutas.forEach((fruta, idx) => {
                const zebra = idx % 2 === 1 ? ' captacao-matriz-col-zebra' : '';
                totaisHtml += `<td class="matriz-total-celula${zebra}" data-fruta-id="${fruta.id}"></td>`;
            });
            if (emCaptacao) {
                totaisHtml += '<td></td><td></td>';
            }
            trTotais.innerHTML = totaisHtml;
            fragment.appendChild(trTotais);
        }

        tbody.insertBefore(fragment, rowAdicionar);
        atualizarLinhaAdicionar(data.frutas);

        bindCelulas();
        bindNumeroPedido();
        bindConcluirButtons(tbody);
        bindRemoverLojaButtons(tbody);
        bindSaidaFisicaVendaRadios(tbody);
        initMatrizTooltips(tbody);
        atualizarTotais();

        if (data.linhas_rotas) {
            renderRotasTabela(data.linhas_rotas);
        }
        if (data.grupos_ordem_carregamento) {
            renderOrdemCarregamento(data.grupos_ordem_carregamento);
        }
        if (Array.isArray(data.rotas)) {
            rotasOptions = data.rotas;
        }
        if (Array.isArray(data.veiculos)) {
            veiculosOptions = data.veiculos;
        }

        matrizVersion = data.version ?? matrizVersion;
    }

    function resetSelectNovaLoja() {
        if (!selectLoja) {
            return;
        }

        if (window.jQuery?.fn?.select2 && window.jQuery(selectLoja).hasClass('select2-hidden-accessible')) {
            const $select = window.jQuery(selectLoja);
            $select.prop('disabled', false);
            $select.val(null).trigger('change');
            return;
        }

        selectLoja.disabled = false;
        selectLoja.value = '';
    }

    async function adicionarLojaNaMatriz(idCliente) {
        if (!idCliente || !selectLoja || adicionandoLoja) {
            return;
        }

        adicionandoLoja = true;
        setBadge('sincronizando');
        if (window.jQuery?.fn?.select2 && window.jQuery(selectLoja).hasClass('select2-hidden-accessible')) {
            window.jQuery(selectLoja).prop('disabled', true);
        } else {
            selectLoja.disabled = true;
        }

        try {
            const res = await fetch(urlAdicionarLoja, {
                method: 'POST',
                headers: headersJson(),
                body: JSON.stringify({ id_cliente: Number(idCliente) }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível adicionar a loja');
                resetSelectNovaLoja();
                setBadge('erro');
                return;
            }

            const data = await res.json();
            aplicarRespostaAlteracaoLoja(data);
        } catch (e) {
            mostrarErro('Erro ao adicionar loja.');
            resetSelectNovaLoja();
            setBadge('erro');
        } finally {
            adicionandoLoja = false;
        }
    }

    function aplicarRespostaAlteracaoLoja(data) {
        reconstruirMatrizCaptacao(data);
        if (data.clientes_disponiveis) {
            atualizarSelectLojasDisponiveis(data.clientes_disponiveis);
        }
        resetSelectNovaLoja();
        setBadge('sincronizado');
    }

    async function removerLojaNaMatriz(idCliente, nomeLoja) {
        if (!idCliente || removendoLoja) {
            return;
        }

        const nome = (nomeLoja || '').trim() || 'esta loja';
        const confirmar = typeof window.AdminConfirm?.confirm === 'function'
            ? await window.AdminConfirm.confirm({
                title: 'Remover loja',
                message: `Deseja remover a loja «${nome}» da captação? As quantidades e preços desta loja no lote serão excluídos.`,
                variant: 'warning',
                confirmLabel: 'Remover',
                cancelLabel: 'Cancelar',
            })
            : window.confirm(`Deseja remover a loja «${nome}» da captação?`);

        if (!confirmar) {
            return;
        }

        removendoLoja = true;
        setBadge('sincronizando');

        try {
            const res = await fetch(urlRemoverLoja, {
                method: 'POST',
                headers: headersJson(),
                body: JSON.stringify({ id_cliente: Number(idCliente) }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível remover a loja');
                setBadge('erro');
                return;
            }

            const data = await res.json();
            aplicarRespostaAlteracaoLoja(data);
        } catch (e) {
            mostrarErro('Erro ao remover loja.');
            setBadge('erro');
        } finally {
            removendoLoja = false;
        }
    }

    function bindRemoverLojaButtons(root) {
        (root || document).querySelectorAll('.btn-matriz-remover:not([data-bound-remover])').forEach((btn) => {
            btn.dataset.boundRemover = '1';
            btn.addEventListener('click', function () {
                removerLojaNaMatriz(this.dataset.clienteId, this.dataset.clienteNome);
            });
        });
    }

    function bindSelectNovaLoja() {
        if (!selectLoja) {
            return;
        }

        if (window.jQuery?.fn?.select2) {
            const $select = window.jQuery(selectLoja);
            $select.off('select2:select.adicionarLoja');
            $select.on('select2:select.adicionarLoja', function (event) {
                const id = event?.params?.data?.id ?? this.value;
                adicionarLojaNaMatriz(id);
            });
            return;
        }

        selectLoja.addEventListener('change', function () {
            adicionarLojaNaMatriz(this.value);
        });
    }

    function celulaQtyBloqueada(qty) {
        return !qty || qty.disabled || qty.readOnly;
    }

    function celulaPrecoBloqueada(preco) {
        return !preco || preco.disabled || preco.readOnly;
    }

    function limparPrecoSeQuantidadeVazia(stack) {
        const qty = stack?.querySelector('.captacao-celula-qty');
        const preco = stack?.querySelector('.captacao-celula-preco');
        if (!qty || qty.value !== '') {
            return;
        }
        if (preco) {
            preco.value = '';
            delete preco.dataset.rawDigitos;
        }
    }

    function limparCelulaNaoPedida(stack) {
        const qty = stack?.querySelector('.captacao-celula-qty');
        const preco = stack?.querySelector('.captacao-celula-preco');
        if (qty) {
            qty.value = '';
        }
        if (preco) {
            preco.value = '';
            delete preco.dataset.rawDigitos;
        }
        stack?.querySelectorAll('.captacao-celula').forEach((inp) => {
            inp.classList.remove('is-valid', 'is-invalid');
        });
    }

    async function salvarCelula(origem, payload) {
        const stack = stackFromInput(origem);
        const qty = stack?.querySelector('.captacao-celula-qty');
        const preco = stack?.querySelector('.captacao-celula-preco');
        const editandoPreco = origem?.classList?.contains('captacao-celula-preco');

        if (!qty) {
            return;
        }

        if (celulaQtyBloqueada(qty) && celulaPrecoBloqueada(preco)) {
            return;
        }

        if (celulaQtyBloqueada(qty) && !editandoPreco) {
            return;
        }

        if (editandoPreco && celulaPrecoBloqueada(preco)) {
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
            if (data.removido) {
                limparCelulaNaoPedida(stack);
            } else if (data.item) {
                aplicarVersionCelula(stack, data.item.version);
                stack.querySelectorAll('.captacao-celula').forEach((inp) => {
                    inp.classList.remove('is-invalid');
                    inp.classList.add('is-valid');
                });
            }
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
            quantidade: qty.value === '' ? null : Number(qty.value),
            preco_venda: qty.value === '' ? null : precoValorNumerico(preco),
            version: qty.dataset.version ? Number(qty.dataset.version) : null,
        };
    }

    function bindCelulas() {
        document.querySelectorAll('.captacao-celula-qty:not(:disabled)').forEach((input) => {
            if (input.dataset.bound) return;
            input.dataset.bound = '1';

            input.addEventListener('input', () => {
                if (input.disabled || input.readOnly) return;
                limparPrecoSeQuantidadeVazia(stackFromInput(input));
            });

            input.addEventListener('change', () => {
                if (input.disabled || input.readOnly) return;
                limparPrecoSeQuantidadeVazia(stackFromInput(input));
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

    function atualizarEdicaoSaidaFisicaLoja(clienteId, concluida) {
        if (!emCaptacao) {
            return;
        }

        document.querySelectorAll(`[data-saida-fisica-loja="${clienteId}"] .captacao-saida-fisica-radio`).forEach((radio) => {
            radio.disabled = concluida;
            if (concluida) {
                radio.setAttribute('disabled', 'disabled');
            } else {
                radio.removeAttribute('disabled');
            }
        });
    }

    function aplicarConclusaoLinha(clienteId, concluida) {
        const row = document.querySelector(`tr.matriz-row-loja[data-cliente-id="${clienteId}"]`);
        if (!row) return;

        row.dataset.captacaoConcluida = concluida ? '1' : '0';
        row.classList.toggle('matriz-row-loja-concluida', concluida);

        const btn = row.querySelector('.btn-matriz-concluir');
        if (btn) {
            const rotaId = rotaIdDoPedido(clienteId);
            const bloqueiaReabrir = concluida && pedidoRotaConcluida(rotaId);
            const bloqueado = bloqueiaReabrir;
            const titulo = tituloBtnConcluirLoja(concluida, rotaId);
            const wrap = btn.closest('.captacao-matriz-concluir-wrap');

            btn.dataset.concluida = concluida ? '1' : '0';
            btn.disabled = bloqueado;
            btn.classList.toggle('btn-success', concluida);
            btn.classList.toggle('btn-outline-secondary', !concluida);
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = concluida ? 'ri-arrow-go-back-line' : 'ri-check-line';
            }

            if (bloqueado) {
                if (! wrap) {
                    const span = document.createElement('span');
                    span.className = 'd-inline-block captacao-matriz-concluir-wrap';
                    span.setAttribute('tabindex', '0');
                    span.setAttribute('role', 'button');
                    btn.parentNode?.insertBefore(span, btn);
                    span.appendChild(btn);
                    bindConcluirButtons(span);
                }
                btn.removeAttribute('title');
                btn.removeAttribute('data-bs-toggle');
                btn.removeAttribute('aria-label');
                const wrapAtual = btn.closest('.captacao-matriz-concluir-wrap');
                if (wrapAtual) {
                    wrapAtual.setAttribute('title', titulo);
                    wrapAtual.setAttribute('aria-label', titulo);
                    wrapAtual.setAttribute('data-bs-toggle', 'tooltip');
                    wrapAtual.setAttribute('data-bs-placement', 'top');
                    initMatrizTooltips(wrapAtual);
                }
            } else if (wrap) {
                wrap.replaceWith(btn);
                atualizarLegendaBtnIcone(btn, titulo);
                initMatrizTooltips(btn);
            } else {
                atualizarLegendaBtnIcone(btn, titulo);
            }
        }

        const btnRemover = row.querySelector('.btn-matriz-remover');
        if (btnRemover) {
            btnRemover.classList.toggle('d-none', concluida);
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

        atualizarEdicaoSaidaFisicaLoja(clienteId, concluida);

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
            const selected = Number(selecionada) === Number(rota.id);
            const disabled = rota.concluida && !selected ? ' disabled' : '';
            const sufixo = rota.concluida ? ' (concluída)' : '';
            html += `<option value="${rota.id}"${selected ? ' selected' : ''}${disabled}>${escHtml(rota.nome + sufixo)}</option>`;
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
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-4">Nenhum item com quantidade informada. Use a aba <strong>Captação</strong> para captar pedidos.</td></tr>';
            return;
        }

        let html = '';
        linhasRotas.forEach((grupo) => {
            const rowspan = grupo.itens.length;
            grupo.itens.forEach((item, idx) => {
                html += `<tr class="matriz-rotas-row" data-cliente-id="${grupo.id_cliente}" data-fruta-id="${item.id_fruta}">`;
                if (idx === 0) {
                    html += `<td rowspan="${rowspan}" class="align-top"><span class="fw-semibold d-block">${escHtml(grupo.loja_nome)}</span>`;
                    if (grupo.numero_pedido) {
                        html += `<div class="text-muted small">Pedido ${escHtml(grupo.numero_pedido)}</div>`;
                    }
                    if (grupo.saida_fisica_nome) {
                        html += `<div class="text-muted small">${escHtml(grupo.saida_fisica_nome)}</div>`;
                    }
                    html += '</td>';
                }
                html += `<td>${escHtml(item.fruta_nome)} <span class="text-muted small">(${escHtml(item.unidade_medicao)})</span></td>`;
                html += `<td class="text-end matriz-rotas-qty">${formatarQuantidadeExibicao(item.quantidade)}</td>`;
                html += `<td class="text-end matriz-rotas-preco">${formatarPrecoExibicao(item.preco_venda)}</td>`;
                if (idx === 0) {
                    const rotaAtual = rotasOptions.find((r) => Number(r.id) === Number(grupo.id_captacao_rota));
                    const rotaAtualConcluida = Boolean(rotaAtual?.concluida);
                    const podeEditarVinculo = permiteVinculoRota && !rotaAtualConcluida;
                    if (podeEditarVinculo) {
                        const url = @json(url('admin/captacao/lotes/'.$lote->id.'/pedidos')) + `/${grupo.id_cliente}/rota`;
                        html += `<td rowspan="${rowspan}" class="align-top">`;
                        html += `<select class="form-select form-select-sm matriz-rota-select" data-search-select data-placeholder="Selecione ou pesquise a rota" data-cliente="${grupo.id_cliente}" data-url="${url}">`;
                        html += optionsRotasHtml(grupo.id_captacao_rota);
                        html += '</select></td>';
                    } else {
                        let rotulo = escHtml(rotaAtual?.nome ?? '—');
                        if (rotaAtualConcluida) {
                            rotulo += ' <span class="badge bg-success-subtle text-success ms-1">Concluída</span>';
                        }
                        html += `<td rowspan="${rowspan}" class="align-top">${rotulo}</td>`;
                    }
                }
                html += '</tr>';
            });
        });

        tbody.innerHTML = html;
        initCaptacaoSearchSelects(tbody);
        bindRotasHandlers();
        atualizarAlertaRotasVazias();
    }

    function containerSelect2Rota(select) {
        const proximo = select?.nextElementSibling;

        return proximo?.classList.contains('select2-container') ? proximo : null;
    }

    /** @param {'valid'|'invalid'|null} estado */
    function aplicarFeedbackRotaSelect(select, estado) {
        if (!select) {
            return;
        }

        select.classList.remove('is-valid', 'is-invalid');
        const container = containerSelect2Rota(select);
        container?.classList.remove('matriz-rota-select2--salvo', 'matriz-rota-select2--erro');

        if (estado === 'valid') {
            select.classList.add('is-valid');
            container?.classList.add('matriz-rota-select2--salvo');
        } else if (estado === 'invalid') {
            select.classList.add('is-invalid');
            container?.classList.add('matriz-rota-select2--erro');
        }
    }

    function matrizRotaSelectEmEdicao(clienteId) {
        return Array.from(document.querySelectorAll(`.matriz-rota-select[data-cliente="${clienteId}"]`))
            .some((select) => {
                if (document.activeElement === select) {
                    return true;
                }

                const instancia = window.jQuery?.(select)?.data('select2');

                return Boolean(instancia?.isOpen?.());
            });
    }

    function bindRotasHandlers() {
        const tbody = document.getElementById('matriz-rotas-body');
        if (!tbody || !window.jQuery) {
            return;
        }

        const $tbody = window.jQuery(tbody);
        $tbody.off(
            'change.matrizRota select2:select.matrizRota select2:clear.matrizRota',
            '.matriz-rota-select',
        );
        $tbody.on('select2:opening.matrizRota', '.matriz-rota-select', function () {
            aplicarFeedbackRotaSelect(this, null);
        });
        $tbody.on(
            'change.matrizRota select2:select.matrizRota select2:clear.matrizRota',
            '.matriz-rota-select',
            function () {
                salvarRota(this);
            },
        );
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

    function matrizVeiculoSelectEmEdicao(rotaId) {
        return Array.from(document.querySelectorAll(`.matriz-rota-veiculo[data-rota="${rotaId}"]`))
            .some((select) => {
                if (document.activeElement === select) {
                    return true;
                }

                return Boolean(window.jQuery?.(select)?.data('select2')?.isOpen?.());
            });
    }

    function slugAbaRota(rotaId) {
        return `rota-${rotaId}`;
    }

    function urlMatrizAbaRota(rotaId) {
        const url = new URL(urlMatrizPorRota, window.location.origin);
        url.searchParams.set('aba', slugAbaRota(rotaId));
        return url.toString();
    }

    function matrizNavRotasLi() {
        return document.getElementById('matriz-nav-rotas')?.closest('li') ?? null;
    }

    function matrizTabContent() {
        return document.querySelector('.captacao-matriz-tabela-card > .tab-content');
    }

    function matrizRotasTabPane() {
        return document.getElementById('matriz-tab-rotas');
    }

    function registrarMatrizAbaLink(link) {
        if (!link || link.dataset.matrizAbaBound === '1') {
            return;
        }

        link.dataset.matrizAbaBound = '1';
        link.addEventListener('shown.bs.tab', () => {
            sincronizarUrlMatrizAba(link.dataset.matrizAba);
            pollEstado();
            agendarPollEstado();
        });
    }

    function registrarMatrizAbaLinks(scope = document) {
        scope.querySelectorAll('[data-matriz-aba]').forEach((link) => registrarMatrizAbaLink(link));
    }

    function ativarMatrizAba(aba) {
        if (!aba) {
            return;
        }

        const link = document.querySelector(`[data-matriz-aba="${aba}"]`);
        if (!link || link.classList.contains('active') || typeof bootstrap === 'undefined') {
            return;
        }

        bootstrap.Tab.getOrCreateInstance(link).show();
        sincronizarUrlMatrizAba(aba);
    }

    function htmlNavRotaVinculada(grupoRota, ativa) {
        const aba = slugAbaRota(grupoRota.id_captacao_rota);
        const badge = grupoRota.concluida
            ? '<span class="badge bg-success-subtle text-success ms-1">✓</span>'
            : '';

        return `<li class="nav-item matriz-nav-rota-vinculada" role="presentation" data-rota-id="${grupoRota.id_captacao_rota}">
            <a class="nav-link${ativa ? ' active' : ''}"
               id="matriz-nav-${aba}"
               data-bs-toggle="tab"
               data-matriz-aba="${aba}"
               href="#matriz-tab-${aba}"
               role="tab"
               aria-controls="matriz-tab-${aba}"
               aria-selected="${ativa ? 'true' : 'false'}">${escHtml(grupoRota.rota_nome)}${badge}</a>
        </li>`;
    }

    function htmlRotaCabecalho(grupoRota, rotaConcluida, podeEditarRota, urlConcluir, urlReabrir, urlMotorista, urlVeiculo, urlRomaneioPdf) {
        let html = '<div class="matriz-rota-cabecalho mb-3">';
        html += htmlRotaTituloLinha(grupoRota, rotaConcluida, podeEditarRota, urlConcluir, urlReabrir, urlRomaneioPdf);

        if (podeEditarRota) {
            html += '<div class="matriz-rota-cabecalho-campos">';
            html += `<input type="text" class="form-control form-control-sm matriz-rota-motorista" maxlength="120" placeholder="Motorista" data-rota="${grupoRota.id_captacao_rota}" data-url="${urlMotorista}" value="${escHtml(grupoRota.motorista_nome ?? '')}">`;
            html += `<select class="form-select form-select-sm matriz-rota-veiculo" data-search-select data-placeholder="Selecione ou pesquise o veículo" data-rota="${grupoRota.id_captacao_rota}" data-url="${urlVeiculo}">`;
            html += optionsVeiculosHtml(grupoRota.id_veiculo, grupoRota.id_captacao_rota);
            html += '</select></div>';
        } else {
            html += '<div class="matriz-rota-resumo small text-muted">';
            if (grupoRota.motorista_nome) {
                html += `<div>Motorista: ${escHtml(grupoRota.motorista_nome)}</div>`;
            }
            if (grupoRota.veiculo_rotulo) {
                html += `<div>Veículo: ${escHtml(grupoRota.veiculo_rotulo)}</div>`;
            }
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function htmlOrdemCarregamentoRows(grupoRota, podeEditarRota) {
        let html = '';

        grupoRota.lojas.forEach((loja) => {
            const lojaRowspan = loja.itens.length;
            loja.itens.forEach((item, idx) => {
                html += `<tr class="matriz-ordem-row" data-rota-id="${grupoRota.id_captacao_rota}" data-cliente-id="${loja.id_cliente}" data-fruta-id="${item.id_fruta}">`;
                if (idx === 0) {
                    if (podeEditarRota) {
                        const url = @json(url('admin/captacao/lotes/'.$lote->id.'/pedidos')) + `/${loja.id_cliente}/ordem-carregamento`;
                        html += `<td rowspan="${lojaRowspan}" class="align-top">`;
                        html += `<select class="form-select form-select-sm matriz-ordem-select" data-search-select data-placeholder="Ordem de carregamento" data-cliente="${loja.id_cliente}" data-rota="${grupoRota.id_captacao_rota}" data-total-lojas="${grupoRota.total_lojas}" data-url="${url}">`;
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

        return html;
    }

    function htmlPaneRotaVinculada(grupoRota, ativa) {
        const aba = slugAbaRota(grupoRota.id_captacao_rota);
        const rotaConcluida = Boolean(grupoRota.concluida);
        const podeEditarRota = permiteVinculoRota && !rotaConcluida;
        const urlBaseRotas = @json(url('admin/captacao/lotes/'.$lote->id.'/rotas'));
        const urlConcluir = `${urlBaseRotas}/${grupoRota.id_captacao_rota}/concluir`;
        const urlReabrir = `${urlBaseRotas}/${grupoRota.id_captacao_rota}/reabrir`;
        const urlMotorista = `${urlBaseRotas}/${grupoRota.id_captacao_rota}/motorista`;
        const urlVeiculo = `${urlBaseRotas}/${grupoRota.id_captacao_rota}/veiculo`;
        const urlRomaneioPdf = `${urlBaseRotas}/${grupoRota.id_captacao_rota}/romaneio.pdf`;

        let html = `<div class="tab-pane fade matriz-tab-pane-rota-vinculada${ativa ? ' show active' : ''}" id="matriz-tab-${aba}" role="tabpanel" data-rota-id="${grupoRota.id_captacao_rota}">`;
        html += '<div class="card-body table-responsive pt-3 pb-3">';
        html += '<p class="text-muted small mb-2">Lojas com quantidade captada nesta rota. Defina a ordem de carregamento e use <strong>Concluir</strong> para impedir novos vínculos; <strong>Reabrir</strong> libera alterações novamente.</p>';
        html += htmlRotaCabecalho(grupoRota, rotaConcluida, podeEditarRota, urlConcluir, urlReabrir, urlMotorista, urlVeiculo, urlRomaneioPdf);
        html += '<table class="table table-bordered table-sm align-middle matriz-ordem-rota-table"><thead><tr>';
        html += '<th style="width:8rem">Ordem de Carregamento</th>';
        html += '<th style="min-width:11rem">Loja</th><th>Item</th><th class="text-end" style="width:6rem">Qtd (UM)</th>';
        html += '</tr></thead>';
        html += `<tbody class="matriz-ordem-body" data-rota-id="${grupoRota.id_captacao_rota}">`;
        html += htmlOrdemCarregamentoRows(grupoRota, podeEditarRota);
        html += '</tbody></table></div></div>';

        return html;
    }

    function bindVeiculoRotaHandlers() {
        const root = matrizTabContent();
        if (!root || !window.jQuery) {
            return;
        }

        const $root = window.jQuery(root);
        $root.off(
            'change.matrizVeiculo select2:select.matrizVeiculo select2:clear.matrizVeiculo',
            '.matriz-rota-veiculo',
        );
        $root.on(
            'change.matrizVeiculo select2:select.matrizVeiculo select2:clear.matrizVeiculo',
            '.matriz-rota-veiculo',
            function () {
                salvarVeiculoRota(this);
            },
        );
    }

    function atualizarSelectsVeiculoRotas(rotaEmEdicao = null) {
        document.querySelectorAll('.matriz-rota-veiculo').forEach((select) => {
            const rotaId = Number(select.dataset.rota);
            if (rotaEmEdicao !== null && rotaId === Number(rotaEmEdicao) && matrizVeiculoSelectEmEdicao(rotaId)) {
                return;
            }

            const valorAtual = select.value === '' ? null : Number(select.value);
            destroyCaptacaoSearchSelect(select);
            select.innerHTML = optionsVeiculosHtml(valorAtual, rotaId);
            select.value = valorAtual ? String(valorAtual) : '';
            initCaptacaoSearchSelects(select);
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

    function bindOrdemCarregamentoHandlers() {
        const root = matrizTabContent();
        if (!root || !window.jQuery) {
            return;
        }

        const $root = window.jQuery(root);
        $root.off('change.matrizOrdem select2:select.matrizOrdem', '.matriz-ordem-select');
        $root.on('change.matrizOrdem select2:select.matrizOrdem', '.matriz-ordem-select', function () {
            if (ignorarOrdemChange) {
                return;
            }
            agendarSalvarOrdemCarregamento(this);
        });
    }

    function agendarSalvarOrdemCarregamento(select) {
        const clienteId = select.dataset.cliente || select.getAttribute('data-cliente');
        if (!clienteId) {
            return;
        }

        clearTimeout(ordemSaveTimers.get(clienteId));
        ordemSaveTimers.set(clienteId, setTimeout(() => {
            ordemSaveTimers.delete(clienteId);
            salvarOrdemCarregamento(select);
        }, 80));
    }

    function htmlRotaTituloLinha(grupoRota, rotaConcluida, podeEditarRota, urlConcluir, urlReabrir, urlRomaneioPdf) {
        let html = '<div class="matriz-rota-titulo">';
        html += '<div class="matriz-rota-titulo-esq">';
        html += `<span class="fw-semibold text-nowrap">${escHtml(grupoRota.rota_nome)}</span>`;
        if (rotaConcluida) {
            html += '<span class="badge bg-success-subtle text-success">Concluída</span>';
        }
        html += '</div><div class="matriz-rota-titulo-dir">';
        if (podeEditarRota) {
            const podeConcluir = Boolean(grupoRota.pode_concluir);
            const pendencias = Array.isArray(grupoRota.pendencias_conclusao) ? grupoRota.pendencias_conclusao : [];
            const tituloConcluir = podeConcluir ? 'Concluir rota' : 'Pendências para concluir a rota';
            const classePendente = podeConcluir ? '' : ' btn-matriz-rota-concluir--pendente';
            const pendenciasJson = JSON.stringify(pendencias).replace(/'/g, '&#39;');
            html += `<button type="button" class="btn btn-soft-success btn-sm btn-matriz-icon btn-matriz-rota-concluir${classePendente}" data-rota="${grupoRota.id_captacao_rota}" data-rota-nome="${escHtml(grupoRota.rota_nome)}" data-url="${urlConcluir}" data-pendencias='${pendenciasJson}' title="${escHtml(tituloConcluir)}" aria-label="${escHtml(tituloConcluir)}" data-bs-toggle="tooltip" data-bs-placement="top"><i class="ri-check-line" aria-hidden="true"></i></button>`;
        } else if (rotaConcluida && permiteVinculoRota) {
            html += `<a href="${urlRomaneioPdf}" class="btn btn-soft-primary btn-sm btn-matriz-icon" title="Baixar romaneio em PDF" aria-label="Baixar romaneio em PDF" data-bs-toggle="tooltip" data-bs-placement="top"><i class="ri-file-download-line" aria-hidden="true"></i></a>`;
            html += `<button type="button" class="btn btn-soft-warning btn-sm btn-matriz-icon btn-matriz-rota-reabrir" data-rota="${grupoRota.id_captacao_rota}" data-rota-nome="${escHtml(grupoRota.rota_nome)}" data-url="${urlReabrir}" title="Reabrir rota" aria-label="Reabrir rota" data-bs-toggle="tooltip" data-bs-placement="top"><i class="ri-arrow-go-back-line" aria-hidden="true"></i></button>`;
        }
        html += '</div></div>';

        return html;
    }

    function renderOrdemCarregamento(grupos) {
        const rotasPane = matrizRotasTabPane();
        const navRotasLi = matrizNavRotasLi();
        if (!rotasPane || !navRotasLi) {
            return;
        }

        const abaAtual = new URL(window.location.href).searchParams.get('aba')
            || document.querySelector('[data-matriz-aba].active')?.dataset.matrizAba
            || '';
        const slugsAtuais = (grupos || []).map((grupo) => slugAbaRota(grupo.id_captacao_rota));
        let abaManter = abaAtual;

        if (abaAtual.startsWith('rota-') && !slugsAtuais.includes(abaAtual)) {
            abaManter = slugsAtuais[0] || 'rotas';
        }

        ignorarOrdemChange = true;

        document.querySelectorAll('.matriz-nav-rota-vinculada').forEach((item) => item.remove());
        document.querySelectorAll('.matriz-tab-pane-rota-vinculada').forEach((pane) => pane.remove());

        if (!grupos?.length) {
            ignorarOrdemChange = false;

            if (abaAtual.startsWith('rota-')) {
                ativarMatrizAba('rotas');
            }

            return;
        }

        const navHtml = grupos.map((grupo) => htmlNavRotaVinculada(
            grupo,
            slugAbaRota(grupo.id_captacao_rota) === abaManter,
        )).join('');
        navRotasLi.insertAdjacentHTML('afterend', navHtml);

        const motoristaEmEdicao = document.querySelector('.matriz-rota-motorista:focus');
        const rotaMotoristaEmEdicao = motoristaEmEdicao?.dataset.rota ?? null;
        const valorMotoristaEmEdicao = motoristaEmEdicao?.value ?? '';
        const veiculoEmEdicao = document.querySelector('.matriz-rota-veiculo:focus');
        const rotaVeiculoEmEdicao = veiculoEmEdicao?.dataset.rota ?? null;
        const valorVeiculoEmEdicao = veiculoEmEdicao?.value ?? '';

        rotasPane.insertAdjacentHTML('afterend', grupos.map((grupo) => htmlPaneRotaVinculada(
            grupo,
            slugAbaRota(grupo.id_captacao_rota) === abaManter,
        )).join(''));

        const panesHost = matrizTabContent();
        initCaptacaoSearchSelects(panesHost);
        bindOrdemCarregamentoHandlers();
        bindVeiculoRotaHandlers();
        bindRotaConcluirReabrirHandlers();
        initMatrizTooltips(panesHost);
        registrarMatrizAbaLinks(navRotasLi.parentElement);

        ignorarOrdemChange = false;

        if (abaManter.startsWith('rota-') && abaManter !== abaAtual) {
            ativarMatrizAba(abaManter);
        }

        if (rotaMotoristaEmEdicao) {
            const input = panesHost?.querySelector(`.matriz-rota-motorista[data-rota="${rotaMotoristaEmEdicao}"]`);
            if (input) {
                input.value = valorMotoristaEmEdicao;
                input.focus();
            }
        }

        if (rotaVeiculoEmEdicao) {
            const select = panesHost?.querySelector(`.matriz-rota-veiculo[data-rota="${rotaVeiculoEmEdicao}"]`);
            if (select) {
                select.value = valorVeiculoEmEdicao;
                select.focus();
            }
        }
    }

    async function salvarVeiculoRota(select) {
        if (select.disabled || select.dataset.salvandoVeiculo === '1') {
            return;
        }

        select.dataset.salvandoVeiculo = '1';
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
            await sincronizarGruposOrdemCarregamento();
        } catch (e) {
            select.classList.add('is-invalid');
            setBadge('erro');
        } finally {
            delete select.dataset.salvandoVeiculo;
        }
    }

    function bindRotaConcluirReabrirHandlers() {
        const root = matrizTabContent();
        if (!root || root.dataset.boundRotaConcluir === '1') {
            return;
        }

        root.dataset.boundRotaConcluir = '1';
        root.addEventListener('click', async (event) => {
            const btnConcluir = event.target.closest('.btn-matriz-rota-concluir');
            if (btnConcluir) {
                let pendencias = pendenciasDoBotao(btnConcluir);
                const pendenciasServidor = await buscarPendenciasConclusaoRota(btnConcluir);
                if (pendenciasServidor !== null) {
                    pendencias = pendenciasServidor;
                    btnConcluir.dataset.pendencias = JSON.stringify(pendencias);
                }

                if (pendencias.length > 0) {
                    matrizShowToast(formatarPendenciasTexto(pendencias), 'warning');
                    return;
                }

                alterarStatusRota(btnConcluir, 'concluir');
                return;
            }

            const btnReabrir = event.target.closest('.btn-matriz-rota-reabrir');
            if (btnReabrir) {
                alterarStatusRota(btnReabrir, 'reabrir');
            }
        });
    }

    async function alterarStatusRota(btn, acao) {
        if (btn.disabled || btn.dataset.processando === '1') {
            return;
        }

        const nomeRota = btn.dataset.rotaNome || 'esta rota';

        if (acao === 'concluir') {
            const pendenciasServidor = await buscarPendenciasConclusaoRota(btn);
            if (pendenciasServidor !== null && pendenciasServidor.length > 0) {
                btn.dataset.pendencias = JSON.stringify(pendenciasServidor);
                matrizShowToast(formatarPendenciasTexto(pendenciasServidor), 'warning');
                return;
            }
        }

        const titulo = acao === 'concluir' ? 'Concluir rota' : 'Reabrir rota';
        const mensagem = acao === 'concluir'
            ? `Deseja concluir a rota «${nomeRota}»? Lojas não poderão mais ser vinculadas a ela até reabrir.`
            : `Deseja reabrir a rota «${nomeRota}» para permitir novos vínculos e alterações?`;

        const confirmou = typeof window.AdminConfirm?.confirm === 'function'
            ? await window.AdminConfirm.confirm({
                title: titulo,
                message: mensagem,
                variant: acao === 'concluir' ? 'success' : 'warning',
                confirmLabel: acao === 'concluir' ? 'Concluir' : 'Reabrir',
                cancelLabel: 'Cancelar',
            })
            : window.confirm(mensagem);

        if (!confirmou) {
            return;
        }

        btn.dataset.processando = '1';
        setBadge('sincronizando');

        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: headersJson(),
            });

            if (!res.ok) {
                setBadge('erro');
                const data = await res.json().catch(() => ({}));
                if (acao === 'concluir') {
                    matrizShowToast(mensagemAmigavelErroConcluirRota(data, res.status), 'warning');
                } else {
                    mostrarErro(mensagemAmigavelErroConcluirRota(data, res.status));
                }
                return;
            }

            const data = await res.json();
            aplicarEstado(data);
            setBadge('sincronizado');
        } catch (e) {
            setBadge('erro');
        } finally {
            delete btn.dataset.processando;
        }
    }

    async function sincronizarGruposOrdemCarregamento() {
        try {
            const estadoRes = await fetch(urlEstado, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!estadoRes.ok) {
                return;
            }

            const data = await estadoRes.json();
            matrizVersion = data.version;
            if (data.grupos_ordem_carregamento) {
                renderOrdemCarregamento(data.grupos_ordem_carregamento);
            }
        } catch (e) {
            /* ignore */
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
            await sincronizarGruposOrdemCarregamento();
        } catch (e) {
            input.classList.add('is-invalid');
            setBadge('erro');
        }
    }

    const matrizRotasVinculadasRoot = matrizTabContent();
    matrizRotasVinculadasRoot?.addEventListener('blur', (event) => {
        const input = event.target.closest('.matriz-rota-motorista');
        if (!input) {
            return;
        }
        salvarMotoristaRota(input);
    }, true);

    matrizRotasVinculadasRoot?.addEventListener('keydown', (event) => {
        const input = event.target.closest('.matriz-rota-motorista');
        if (!input) {
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            input.blur();
        }
    });

    bindRotaConcluirReabrirHandlers();
    bindOrdemCarregamentoHandlers();
    bindVeiculoRotaHandlers();

    async function salvarOrdemCarregamento(select) {
        if (ignorarOrdemChange || select.disabled || select.dataset.salvandoOrdem === '1') {
            return;
        }

        const ordem = select.value === '' ? null : Number(select.value);
        select.dataset.salvandoOrdem = '1';
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

            const payload = await res.json().catch(() => ({}));

            select.classList.remove('is-invalid');
            select.classList.add('is-valid');

            const estadoRes = await fetch(urlEstado, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (estadoRes.ok) {
                const data = await estadoRes.json();
                matrizVersion = data.version;
                if (data.grupos_ordem_carregamento) {
                    renderOrdemCarregamento(data.grupos_ordem_carregamento);
                }
            } else if (Array.isArray(payload.pedidos_rota)) {
                aplicarOrdemCarregamentoPedidos(payload.pedidos_rota);
            }

            setBadge('sincronizado');
        } catch (e) {
            select.classList.add('is-invalid');
            setBadge('erro');
        } finally {
            delete select.dataset.salvandoOrdem;
        }
    }

    function aplicarOrdemCarregamentoPedidos(pedidosRota) {
        pedidosRota.forEach((pedido) => {
            document.querySelectorAll(`.matriz-ordem-select[data-cliente="${pedido.id_cliente}"]`).forEach((sel) => {
                sel.value = pedido.ordem_carregamento ? String(pedido.ordem_carregamento) : '';
                window.AdminSearchSelect?.refresh(sel);
            });
        });
    }

    async function salvarRota(select) {
        if (select.disabled || select.dataset.salvandoRota === '1') {
            return;
        }

        select.dataset.salvandoRota = '1';

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
                aplicarFeedbackRotaSelect(select, 'invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res));
                return;
            }

            aplicarFeedbackRotaSelect(select, 'valid');
            document.querySelectorAll(`.matriz-rota-select[data-cliente="${clienteId}"]`).forEach((outro) => {
                if (outro !== select) {
                    outro.value = select.value;
                }
                window.AdminSearchSelect?.refresh(outro);
                aplicarFeedbackRotaSelect(outro, 'valid');
            });
            window.AdminSearchSelect?.refresh(select);
            aplicarFeedbackRotaSelect(select, 'valid');
            setBadge('sincronizado');
            pollEstado();
        } catch (e) {
            aplicarFeedbackRotaSelect(select, 'invalid');
            setBadge('erro');
        } finally {
            delete select.dataset.salvandoRota;
        }
    }

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

            if (data.conclusao_captacao_lote) {
                aplicarConclusaoCaptacaoLote(data.conclusao_captacao_lote);
            }

            if (data.layout_hash && data.layout_hash !== layoutHash) {
                reconstruirMatrizCaptacao(data);
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

    function aplicarRotasPendentesCaptacao(data) {
        if (! Array.isArray(data.rotas_pendentes_conclusao_captacao)) {
            return;
        }

        rotasPendentesConclusaoCaptacao.length = 0;
        rotasPendentesConclusaoCaptacao.push(...data.rotas_pendentes_conclusao_captacao);
        todasRotasDoLoteConcluidas = rotasPendentesConclusaoCaptacao.length === 0;
        atualizarAlertaRotasPendentesCaptacao();
    }

    function aplicarConclusaoCaptacaoLote(info) {
        if (typeof window.aplicarConclusaoCaptacaoLote === 'function') {
            window.aplicarConclusaoCaptacaoLote(info);
            return;
        }

        const panel = document.getElementById('captacao-concluir-lote-panel');
        if (!panel || !info) {
            return;
        }

        if (info.lote_status === 'CAPTACAO_CONCLUIDA') {
            panel.dataset.loteStatus = info.lote_status;
            panel.querySelector('[data-role="badge-concluido"]')?.classList.remove('d-none');
            panel.querySelector('[data-role="em-andamento"]')?.classList.add('d-none');
            const modal = panel.querySelector('[data-role="modal-pendencias"]');
            if (modal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modal)?.hide();
            }
            return;
        }

        const pendencias = Array.isArray(info.pendencias) ? info.pendencias : [];
        const btnPendencias = panel.querySelector('[data-role="btn-pendencias"]');
        const lista = panel.querySelector('[data-role="lista-pendencias"]');
        const modal = panel.querySelector('[data-role="modal-pendencias"]');
        const btn = panel.querySelector('[data-role="btn-submit"]');
        const form = panel.querySelector('[data-role="form-concluir"]');

        if (btnPendencias) {
            btnPendencias.classList.toggle('d-none', pendencias.length === 0);
        }
        if (lista) {
            lista.innerHTML = pendencias.map((p) => `<li>${escHtml(p)}</li>`).join('');
        }
        if (pendencias.length === 0 && modal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(modal)?.hide();
        }

        const pode = !!info.pode;
        if (btn) {
            btn.disabled = !pode;
            btn.title = pode ? 'Encerrar captação deste lote' : 'Resolva as pendências listadas';
        }
        if (form) {
            form.onsubmit = pode ? null : () => false;
        }
    }

    function aplicarEstado(data) {
        if (data.conclusao_captacao_lote) {
            aplicarConclusaoCaptacaoLote(data.conclusao_captacao_lote);
        }

        aplicarRotasPendentesCaptacao(data);

        Object.entries(data.celulas || {}).forEach(([key, cel]) => {
            const [clienteId, frutaId] = key.split('_');
            const qty = document.querySelector(
                `.captacao-celula-qty[data-cliente="${clienteId}"][data-fruta="${frutaId}"]`
            );
            const preco = document.querySelector(
                `.captacao-celula-preco[data-cliente="${clienteId}"][data-fruta="${frutaId}"]`
            );
            if (!qty) return;
            if (document.activeElement === qty || document.activeElement === preco) return;

            if (!celulaQtyBloqueada(qty)) {
                qty.value = cel.quantidade > 0 ? Number(cel.quantidade) : '';
                qty.dataset.version = cel.version;
            }

            if (preco && !celulaPrecoBloqueada(preco)) {
                if (cel.preco_venda !== null && parseFloat(cel.preco_venda) > 0) {
                    const digitos = String(Math.round(parseFloat(cel.preco_venda) * 100));
                    preco.dataset.rawDigitos = digitos;
                    preco.value = formatarPrecoBr(digitos);
                } else {
                    preco.dataset.rawDigitos = '';
                    preco.value = '';
                }
                preco.dataset.version = cel.version;
            } else if (!celulaQtyBloqueada(qty)) {
                qty.dataset.version = cel.version;
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

            if (!matrizRotaSelectEmEdicao(clienteId)) {
                const rotaValor = pedido.id_captacao_rota ? String(pedido.id_captacao_rota) : '';
                document.querySelectorAll(`.matriz-rota-select[data-cliente="${clienteId}"]`).forEach((select) => {
                    if (select.value === rotaValor) {
                        return;
                    }
                    select.value = rotaValor;
                    window.AdminSearchSelect?.refresh(select);
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

        document.querySelectorAll('tr.matriz-row-loja').forEach((row) => {
            const clienteId = row.dataset.clienteId;
            if (!clienteId) {
                return;
            }

            aplicarConclusaoLinha(clienteId, row.dataset.captacaoConcluida === '1');
        });

        (data.rotas || []).forEach((rota) => {
            if (matrizVeiculoSelectEmEdicao(rota.id)) {
                return;
            }

            document.querySelectorAll(`.matriz-rota-veiculo[data-rota="${rota.id}"]`).forEach((select) => {
                const valor = rota.id_veiculo ? String(rota.id_veiculo) : '';
                if (select.value === valor) {
                    return;
                }
                select.value = valor;
                window.AdminSearchSelect?.refresh(select);
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
        aplicarSaidaFisicaVenda(data);
    }

    function abaSaidaEstoqueFisicoVisivel() {
        const tab = document.getElementById('matriz-tab-saida-estoque-fisico');
        return tab !== null && tab.classList.contains('show');
    }

    function nomeSaidaFisicaPorUnidade(idUnidade) {
        if (idUnidade === null || idUnidade === undefined) {
            return nomeUnidadeGalpaoSaida;
        }

        const id = Number(idUnidade);
        if (id === idUnidadeGalpaoSaida) {
            return nomeUnidadeGalpaoSaida;
        }
        if (idUnidadeHubSaida !== null && id === Number(idUnidadeHubSaida)) {
            return nomeUnidadeHubSaida || 'HUB';
        }

        return '—';
    }

    function aplicarSaidaFisicaVenda(data) {
        Object.entries(data.pedidos || {}).forEach(([clienteId, pedido]) => {
            if (!Object.prototype.hasOwnProperty.call(pedido, 'id_unidade_negocio_saida_venda')) {
                return;
            }

            const celVendas = document.querySelector(`[data-arquivo-cigan-vendas-saida="${clienteId}"]`);
            if (celVendas && !salvandoSaidaFisica.has(clienteId)) {
                celVendas.textContent = nomeSaidaFisicaPorUnidade(pedido.id_unidade_negocio_saida_venda);
            }

            const wrap = document.querySelector(`[data-saida-fisica-loja="${clienteId}"]`);
            if (!wrap) {
                return;
            }

            if (salvandoSaidaFisica.has(clienteId)) {
                return;
            }

            if (wrap.contains(document.activeElement)) {
                return;
            }

            const valorRemoto = pedido.id_unidade_negocio_saida_venda !== null
                ? String(pedido.id_unidade_negocio_saida_venda)
                : '';
            const selecionado = wrap.querySelector('.captacao-saida-fisica-radio:checked');
            if (selecionado && selecionado.value === valorRemoto) {
                return;
            }

            wrap.querySelectorAll('.captacao-saida-fisica-radio').forEach((radio) => {
                radio.checked = radio.value === valorRemoto;
            });
        });
    }

    bindCelulas();

    initCaptacaoSearchSelects();
    bindRotasHandlers();
    bindVeiculoRotaHandlers();
    bindSelectNovaLoja();
    bindRemoverLojaButtons();
    bindOrdemCarregamentoHandlers();
    bindRotaConcluirReabrirHandlers();
    bindSaidaFisicaVendaRadios();
    initMatrizTooltips(document.getElementById('captacao-matriz'));
    initMatrizTooltips(document.getElementById('captacao-matriz-ordem'));

    function bindSaidaFisicaVendaRadios(root) {
        const scope = root || document;
        scope.querySelectorAll('.captacao-saida-fisica-radio').forEach((radio) => {
            if (radio.dataset.boundSaidaFisica === '1') {
                return;
            }
            radio.dataset.boundSaidaFisica = '1';
            radio.addEventListener('change', function () {
                if (!this.checked) {
                    return;
                }
                salvarSaidaFisicaVenda(this);
            });
        });
    }

    async function salvarSaidaFisicaVenda(radio) {
        const wrap = radio.closest('[data-saida-fisica-loja]');
        const statusEl = wrap?.querySelector('.captacao-saida-fisica-status');
        const url = radio.dataset.url;
        const idUnidade = Number(radio.value);
        const clienteId = radio.dataset.cliente ? String(radio.dataset.cliente) : null;

        if (!url || !idUnidade) {
            return;
        }

        if (clienteId) {
            salvandoSaidaFisica.add(clienteId);
        }

        if (statusEl) {
            statusEl.textContent = 'Salvando…';
        }

        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify({ id_unidade_negocio_saida_venda: idUnidade }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível salvar');
                if (statusEl) {
                    statusEl.textContent = 'Erro ao salvar';
                }
                return;
            }

            if (statusEl) {
                statusEl.textContent = 'Salvo';
                window.setTimeout(() => {
                    if (statusEl.textContent === 'Salvo') {
                        statusEl.textContent = '';
                    }
                }, 2000);
            }
        } catch (e) {
            mostrarErro('Erro ao salvar saída física.');
            if (statusEl) {
                statusEl.textContent = 'Erro ao salvar';
            }
        } finally {
            if (clienteId) {
                window.setTimeout(() => salvandoSaidaFisica.delete(clienteId), 400);
            }
        }
    }

    function intervaloPollMs() {
        if (document.hidden) {
            return 8000;
        }

        return abaSaidaEstoqueFisicoVisivel() ? 2000 : 3000;
    }

    function agendarPollEstado() {
        if (pollTimer !== null) {
            clearTimeout(pollTimer);
        }

        pollTimer = window.setTimeout(async () => {
            await pollEstado();
            agendarPollEstado();
        }, intervaloPollMs());
    }

    function sincronizarUrlMatrizAba(aba) {
        if (!aba) {
            return;
        }

        const url = new URL(window.location.href);
        if (url.searchParams.get('aba') === aba) {
            return;
        }

        url.searchParams.set('aba', aba);
        history.replaceState(null, '', url.toString());
    }

    function ativarMatrizAbaPorUrl() {
        const aba = new URL(window.location.href).searchParams.get('aba');
        if (!aba) {
            return;
        }

        if (aba === 'por-rota') {
            const primeiraRota = document.querySelector('.matriz-nav-rota-vinculada [data-matriz-aba]');
            if (primeiraRota) {
                bootstrap.Tab.getOrCreateInstance(primeiraRota).show();
            }
            return;
        }

        ativarMatrizAba(aba);
    }

    registrarMatrizAbaLinks();

    window.addEventListener('popstate', () => {
        ativarMatrizAbaPorUrl();
    });

    ativarMatrizAbaPorUrl();

    pollEstado();
    agendarPollEstado();
    document.addEventListener('visibilitychange', () => {
        agendarPollEstado();
    });
})();
</script>
@endpush
