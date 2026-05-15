@extends('layouts.app')

@section('title', 'Praças')
@section('page-title', 'Praças')

@section('content')
    <x-admin.flash-messages />

    @can('pracas.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="pracas-exportacao"
            table-root-id="pracas-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Praças"
        subtitle="Cadastro de praças por unidade de negócio."
        search-placeholder="Pesquisar por nome ou ID da unidade..."
        :endpoint="route('admin.pracas.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="pracas-table"
    >
        <x-slot:actions>
            @can('pracas.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.pracas.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('pracas.importar')
                <a href="{{ route('admin.pracas.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('pracas.criar')
                <a href="{{ route('admin.pracas.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Nova praça
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.pracas._table', [
            'pracas' => $pracas,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
