@extends('layouts.app')

@section('title', 'Fornecedores')
@section('page-title', 'Fornecedores')

@section('content')
    <x-admin.flash-messages />

    @can('fornecedores.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="fornecedores-exportacao"
            table-root-id="fornecedores-table-root"
        />
    @endcan

    <div class="card fornecedores-datatable-card" id="fornecedores-table-root">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Fornecedores</h4>
                <p class="text-muted mb-0">Cadastro mestre de fornecedores (código CIGAM, estado ICMS, razão social, fantasia e CPF/CNPJ).</p>
            </div>
            @can('fornecedores.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.fornecedores.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('fornecedores.importar')
                <a href="{{ route('admin.fornecedores.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('fornecedores.criar')
                <a href="{{ route('admin.fornecedores.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo fornecedor
                </a>
            @endcan
        </div>

        @include('admin.fornecedores._table', [
            'fornecedores' => $fornecedores,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
            'estados' => $estados,
        ])
    </div>
@endsection

@push('head')
    <style>
        .fornecedores-datatable-card .card-header {
            row-gap: 0.75rem;
        }

        .fornecedores-table {
            font-size: 8px;
        }

        .fornecedores-table > :not(caption) > * > * {
            padding: 0.45rem 0.65rem;
            vertical-align: middle;
        }

        .fornecedores-table thead th {
            background: rgba(var(--bs-light-rgb), 0.65);
            color: var(--bs-secondary-color);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .fornecedores-table td {
            line-height: 1.25;
        }

        .fornecedores-table .fornecedor-action-link {
            display: inline-flex;
            font-size: 0.95rem;
            line-height: 1;
            padding: 0.1rem;
            text-decoration: none;
        }

        .fornecedores-table .fornecedor-action-link:hover {
            opacity: 0.75;
        }

        .fornecedores-datatable-card .dataTables_wrapper .row {
            align-items: center;
            row-gap: 0.75rem;
        }

        .fornecedores-datatable-card .fornecedores-datatable-toolbar {
            align-items: center;
        }

        .fornecedores-datatable-card .dataTables_length {
            text-align: left;
        }

        .fornecedores-datatable-card .dataTables_length label {
            align-items: center;
            display: inline-flex;
            gap: 0.45rem;
            margin-bottom: 0;
        }

        .fornecedores-datatable-card .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: center;
        }

        .fornecedores-datatable-card .dataTables_filter input,
        .fornecedores-datatable-card .dataTables_length select {
            font-size: 0.8125rem;
        }

        .fornecedores-datatable-card .dt-buttons .btn {
            font-size: 0.78rem;
            padding: 0.28rem 0.55rem;
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableElement = document.getElementById('fornecedores-datatable');
            if (!tableElement || typeof window.jQuery === 'undefined' || !window.jQuery.fn.DataTable) {
                return;
            }

            const table = window.jQuery(tableElement).DataTable({
                dom: "<'row fornecedores-datatable-toolbar px-3 pt-3'<'col-md-3'l><'col-md-6 text-center'B><'col-md-3'f>>" +
                    "<'row'<'col-12'tr>>" +
                    "<'row px-3 pb-3'<'col-md-5'i><'col-md-7'p>>",
                buttons: [
                    {
                        extend: 'copy',
                        text: 'Copiar',
                        className: 'btn btn-sm btn-light',
                        exportOptions: { columns: ':not(:last-child)' },
                    },
                    {
                        extend: 'print',
                        text: 'Imprimir',
                        className: 'btn btn-sm btn-light',
                        title: 'Fornecedores',
                        exportOptions: { columns: ':not(:last-child)' },
                        customize: function (win) {
                            const css = `
                                @page { margin: 12mm; }
                                body {
                                    color: #1f2937;
                                    font-family: Arial, Helvetica, sans-serif;
                                    font-size: 11px;
                                    line-height: 1.35;
                                }
                                h1 {
                                    border-bottom: 2px solid #0d6efd;
                                    color: #111827;
                                    font-size: 18px;
                                    margin: 0 0 14px;
                                    padding-bottom: 8px;
                                }
                                table {
                                    border-collapse: collapse !important;
                                    width: 100% !important;
                                }
                                thead th {
                                    background: #eef2ff !important;
                                    border: 1px solid #cbd5e1 !important;
                                    color: #1e293b !important;
                                    font-size: 10px;
                                    letter-spacing: .02em;
                                    padding: 6px 7px !important;
                                    text-transform: uppercase;
                                }
                                tbody td {
                                    border: 1px solid #e2e8f0 !important;
                                    padding: 5px 7px !important;
                                    vertical-align: top;
                                }
                                tbody tr:nth-child(even) td {
                                    background: #f8fafc !important;
                                }
                                code {
                                    background: transparent;
                                    color: #111827;
                                    font-family: Arial, Helvetica, sans-serif;
                                    font-size: 10px;
                                }
                            `;
                            win.document.head.insertAdjacentHTML('beforeend', `<style>${css}</style>`);
                        },
                    },
                ],
                keyTable: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                order: [[2, 'asc']],
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false },
                    { targets: [0, 1, 3, 4, 5], className: 'text-nowrap' },
                ],
                language: {
                    search: '',
                    searchPlaceholder: 'ID CIGAM, fornecedor, CPF/CNPJ ou UF',
                    lengthMenu: 'Mostrar _MENU_',
                    info: 'Exibindo _START_ a _END_ de _TOTAL_ fornecedores',
                    infoEmpty: 'Nenhum fornecedor para exibir',
                    infoFiltered: '(filtrado de _MAX_ registros)',
                    zeroRecords: 'Nenhum fornecedor encontrado',
                    emptyTable: 'Nenhum fornecedor cadastrado',
                    paginate: {
                        first: 'Primeiro',
                        previous: 'Anterior',
                        next: 'Próximo',
                        last: 'Último',
                    },
                    buttons: {
                        copy: 'Copiar',
                        print: 'Imprimir',
                    },
                },
            });
        });
    </script>
@endpush
