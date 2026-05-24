@extends('layouts.app')

@section('title', 'Clientes')
@section('page-title', 'Clientes')

@section('content')
    <x-admin.flash-messages />

    @can('clientes.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="clientes-exportacao"
            table-root-id="clientes-table-root"
        />
    @endcan

    <x-admin.datatable
        title="Clientes"
        subtitle="Cadastro mestre de clientes (CIGAM + razão social + fantasia + CPF/CNPJ)."
        table-id="clientes-datatable"
        root-id="clientes-table-root"
        print-title="Clientes"
        entity-label="clientes"
        entity-label-singular="cliente"
        :order="[1, 'asc']"
        :sort-column-map="[
            0 => 'id_cigam',
            1 => 'fantasia',
            2 => 'cnpj_cpf',
            5 => 'desconto_nf',
            6 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [3, 4], 'orderable' => false],
            ['targets' => [0, 1, 2, 5, 6], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('clientes.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.clientes.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('clientes.importar')
                <a href="{{ route('admin.clientes.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('clientes.criar')
                <a href="{{ route('admin.clientes.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo cliente
                </a>
            @endcan
            @canany(['captacao.cliente_fruta.vincular', 'captacao.pedido.editar', 'captacao.lote.visualizar'])
                <a href="{{ route('admin.captacao.frutas-por-loja.index') }}" class="btn btn-soft-primary">
                    <i class="ri-apple-line me-1"></i> Frutas por loja (captação)
                </a>
            @endcanany
        </x-slot:actions>

        @include('admin.clientes._table', [
            'clientes' => $clientes,
        ])
    </x-admin.datatable>
@endsection
