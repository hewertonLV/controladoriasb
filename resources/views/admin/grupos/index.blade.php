@extends('layouts.app')

@section('title', 'Grupos')
@section('page-title', 'Grupos')

@section('content')
    <x-admin.flash-messages />

    @can('grupos.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="grupos-exportacao"
            table-root-id="grupos-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Grupos"
        subtitle="Cadastro de grupos de clientes (nome único, maiúsculas)."
        search-placeholder="Pesquisar por nome..."
        :endpoint="route('admin.grupos.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="grupos-table"
    >
        <x-slot:actions>
            @can('grupos.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.grupos.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('grupos.importar')
                <a href="{{ route('admin.grupos.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('grupos.criar')
                <a href="{{ route('admin.grupos.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo grupo
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.grupos._table', [
            'grupos' => $grupos,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
