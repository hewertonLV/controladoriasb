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

    <x-admin.data-table
        title="Clientes"
        subtitle="Cadastro mestre de clientes (CIGAM + razão social + fantasia + CPF/CNPJ)."
        search-placeholder="Pesquisar por ID CIGAM, razão social, fantasia ou CPF/CNPJ..."
        :endpoint="route('admin.clientes.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'razao_social'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="clientes-table"
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
        </x-slot:actions>

        @include('admin.clientes._table', [
            'clientes' => $clientes,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
