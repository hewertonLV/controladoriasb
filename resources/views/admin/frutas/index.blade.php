@extends('layouts.app')

@section('title', 'Frutas')
@section('page-title', 'Frutas')

@section('content')
    <x-admin.flash-messages />

    @can('frutas.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="frutas-exportacao"
            table-root-id="frutas-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Frutas"
        subtitle="Cadastro mestre de frutas (CIGAM, nome, unidade, kg, ICMS compra/venda e UM do ICMS)."
        search-placeholder="Pesquisar por ID CIGAM, nome, unidade ou UM ICMS..."
        :endpoint="route('admin.frutas.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="frutas-table"
    >
        <x-slot:actions>
            @can('frutas.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.frutas.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('frutas.importar')
                <a href="{{ route('admin.frutas.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('frutas.criar')
                <a href="{{ route('admin.frutas.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Nova fruta
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.frutas._table', [
            'frutas' => $frutas,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
