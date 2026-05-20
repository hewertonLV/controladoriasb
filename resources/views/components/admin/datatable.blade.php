@props([
    'title',
    'subtitle' => null,
    'tableId',
    'rootId' => null,
    'printTitle' => null,
    'entityLabel' => 'registros',
    'entityLabelSingular' => null,
    'order' => [0, 'asc'],
    'pageLength' => 25,
    'sortColumnMap' => null,
    'columnDefs' => null,
    'dataTable' => null,
    'searchPlaceholder' => 'Pesquisar',
])

@php
    $rootId = $rootId ?? $tableId.'-root';
    $printTitle = $printTitle ?? $title;
    $entityLabelSingular = $entityLabelSingular ?? (str_ends_with($entityLabel, 's') ? substr($entityLabel, 0, -1) : $entityLabel);
    $defaultSortKey = is_array($sortColumnMap) ? ($sortColumnMap[$order[0] ?? 0] ?? 'nome_exibicao') : 'nome_exibicao';

    $sortColumnMapJson = is_array($sortColumnMap)
        ? (object) $sortColumnMap
        : null;

    $config = array_filter([
        'title' => $title,
        'printTitle' => $printTitle,
        'entityLabel' => $entityLabel,
        'entityLabelSingular' => $entityLabelSingular,
        'order' => $order,
        'pageLength' => $pageLength,
        'sortColumnMap' => $sortColumnMapJson,
        'columnDefs' => $columnDefs,
        'dataTable' => $dataTable,
        'searchPlaceholder' => $searchPlaceholder,
    ], fn ($value) => $value !== null);
@endphp

<div class="card admin-datatable-card"
     id="{{ $rootId }}"
     data-admin-datatable
     data-table-id="{{ $tableId }}"
     data-config='@json($config)'>
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <h4 class="header-title mb-0">{{ $title }}</h4>
            @if ($subtitle)
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>

    @isset($filters)
        <div class="card-body border-bottom py-3">
            <div class="row g-2 align-items-end">
                {{ $filters }}
            </div>
        </div>
    @endisset

    {{-- Campos ocultos para exportação PDF assíncrona (x-admin.exportacao-pdf-async) --}}
    <input type="hidden" data-table-search value="">
    <input type="hidden" data-table-sort value="{{ $defaultSortKey }}">
    <input type="hidden" data-table-direction value="{{ $order[1] ?? 'asc' }}">
    <input type="hidden" data-table-per-page value="all">

    {{ $slot }}
</div>

@once
    @push('head')
        <style>
            .admin-datatable-card .card-header {
                row-gap: 0.75rem;
            }

            .admin-datatable-table {
                font-size: 8px;
            }

            .admin-datatable-table > :not(caption) > * > * {
                padding: 0.45rem 0.65rem;
                vertical-align: middle;
            }

            .admin-datatable-table thead th {
                background: rgba(var(--bs-light-rgb), 0.65);
                color: var(--bs-secondary-color);
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.025em;
                text-transform: uppercase;
                white-space: normal;
            }

            /* Evita scroll horizontal na toolbar/paginação (margens do .row do Bootstrap). */
            .admin-datatable-card .dataTables_wrapper > .row {
                margin-left: 0;
                margin-right: 0;
            }

            /* Scroll horizontal só na faixa da tabela; ativado via JS quando o conteúdo excede a largura. */
            .admin-datatable-card .admin-datatable-table-scroll {
                overflow-x: visible;
                -webkit-overflow-scrolling: touch;
            }

            .admin-datatable-card .admin-datatable-table-scroll.is-scrollable {
                overflow-x: auto;
            }

            .admin-datatable-card table.dataTable {
                margin-bottom: 0 !important;
                width: 100% !important;
            }

            .admin-datatable-table td {
                line-height: 1.25;
            }

            .admin-datatable-table .admin-datatable-action-link {
                display: inline-flex;
                font-size: 0.95rem;
                line-height: 1;
                padding: 0.1rem;
                text-decoration: none;
            }

            .admin-datatable-table .admin-datatable-action-link:hover {
                opacity: 0.75;
            }

            .admin-datatable-card .dataTables_wrapper .row {
                align-items: center;
                row-gap: 0.75rem;
            }

            .admin-datatable-card .admin-datatable-toolbar {
                align-items: center;
            }

            .admin-datatable-card .dataTables_length {
                text-align: left;
            }

            .admin-datatable-card .dataTables_length label {
                align-items: center;
                display: inline-flex;
                gap: 0.45rem;
                margin-bottom: 0;
            }

            .admin-datatable-card .dt-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
                justify-content: center;
            }

            .admin-datatable-card .dataTables_filter input,
            .admin-datatable-card .dataTables_length select {
                font-size: 0.8125rem;
            }

            .admin-datatable-card .dt-buttons .btn {
                font-size: 0.78rem;
                padding: 0.28rem 0.55rem;
            }

            /* Oculta o aviso padrão do DataTables Buttons (fica no rodapé da página). */
            div.dt-button-info {
                display: none !important;
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
                transform: translateY(-0.25rem) scale(0.98);
            }

            .admin-datatable-toast__content {
                align-items: flex-start;
                display: flex;
                gap: 0.55rem;
                padding: 0.75rem 0.85rem 0.65rem;
            }

            .admin-datatable-toast__content > i:first-child {
                flex-shrink: 0;
                font-size: 1.15rem;
                line-height: 1.35;
            }

            .admin-datatable-toast__message {
                flex: 1;
                font-size: 0.8125rem;
                line-height: 1.4;
                padding-top: 0.05rem;
            }

            .admin-datatable-toast__close {
                background: transparent;
                border: 0;
                flex-shrink: 0;
                line-height: 1;
                opacity: 0.75;
                padding: 0;
            }

            .admin-datatable-toast__close:hover {
                opacity: 1;
            }

            /* Verde — operação concluída com sucesso */
            .admin-datatable-toast--success {
                background-color: #d1e7dd;
                border-color: #75b798;
                color: #0a3622;
            }

            .admin-datatable-toast--success .admin-datatable-toast__content > i:first-child {
                color: #198754;
            }

            .admin-datatable-toast--success .admin-datatable-toast__close {
                color: #0a3622;
            }

            .admin-datatable-toast--success .admin-datatable-toast__progress {
                background-color: #badbcc;
            }

            .admin-datatable-toast--success .admin-datatable-toast__progress > span {
                background-color: #198754;
            }

            /* Vermelho — erro ou ausência de dados */
            .admin-datatable-toast--danger {
                background-color: #f8d7da;
                border-color: #ea868f;
                color: #58151c;
            }

            .admin-datatable-toast--danger .admin-datatable-toast__content > i:first-child {
                color: #dc3545;
            }

            .admin-datatable-toast--danger .admin-datatable-toast__close {
                color: #58151c;
            }

            .admin-datatable-toast--danger .admin-datatable-toast__progress {
                background-color: #f1aeb5;
            }

            .admin-datatable-toast--danger .admin-datatable-toast__progress > span {
                background-color: #dc3545;
            }

            /* Azul — informação (ex.: copiado com sucesso) */
            .admin-datatable-toast--info {
                background-color: #cff4fc;
                border-color: #6edff6;
                color: #055160;
            }

            .admin-datatable-toast--info .admin-datatable-toast__content > i:first-child {
                color: #0dcaf0;
            }

            .admin-datatable-toast--info .admin-datatable-toast__close {
                color: #055160;
            }

            .admin-datatable-toast--info .admin-datatable-toast__progress {
                background-color: #9eeaf9;
            }

            .admin-datatable-toast--info .admin-datatable-toast__progress > span {
                background-color: #0aa2c0;
            }

            /* Amarelo — aviso (uso opcional) */
            .admin-datatable-toast--warning {
                background-color: #fff3cd;
                border-color: #ffda6a;
                color: #664d03;
            }

            .admin-datatable-toast--warning .admin-datatable-toast__content > i:first-child {
                color: #ffc107;
            }

            .admin-datatable-toast--warning .admin-datatable-toast__close {
                color: #664d03;
            }

            .admin-datatable-toast--warning .admin-datatable-toast__progress {
                background-color: #ffe69c;
            }

            .admin-datatable-toast--warning .admin-datatable-toast__progress > span {
                background-color: #ffc107;
            }

            .admin-datatable-toast__progress {
                height: 3px;
                width: 100%;
            }

            .admin-datatable-toast__progress > span {
                display: block;
                height: 100%;
                transform-origin: left center;
                width: 100%;
                animation: admin-datatable-toast-progress linear forwards;
            }

            @keyframes admin-datatable-toast-progress {
                from {
                    transform: scaleX(1);
                }
                to {
                    transform: scaleX(0);
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script src="{{ asset('assets/vendor/datatables.net/js/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/buttons.html5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-buttons/js/buttons.print.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/datatables.net-keytable/js/dataTables.keyTable.min.js') }}"></script>
        @php
            $adminDatatableJsPath = public_path('assets/js/admin-datatable.js');
            $adminDatatableJsVersion = is_file($adminDatatableJsPath) ? (string) filemtime($adminDatatableJsPath) : '';
        @endphp
        <script src="{{ asset('assets/js/admin-datatable.js') }}{{ $adminDatatableJsVersion !== '' ? '?v='.$adminDatatableJsVersion : '' }}"></script>
    @endpush
@endonce
