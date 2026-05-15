@extends('layouts.app')

@section('title', 'Veículos')
@section('page-title', 'Veículos')

@section('content')
    <x-admin.flash-messages />

    @can('veiculos.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="veiculos-exportacao"
            table-root-id="veiculos-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Veículos"
        subtitle="Cadastro de veículos (SBS, nome, tipo e unidade de negócio)."
        search-placeholder="Pesquisar por ID SBS, nome, tipo..."
        :endpoint="route('admin.veiculos.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="veiculos-table"
    >
        <x-slot:actions>
            @can('veiculos.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.veiculos.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('veiculos.importar')
                <a href="{{ route('admin.veiculos.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('veiculos.criar')
                <a href="{{ route('admin.veiculos.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo veículo
                </a>
            @endcan
        </x-slot:actions>

        <x-slot:filters>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="veiculos-status">Status</label>
                <select id="veiculos-status" name="status" class="form-select" data-table-filter>
                    <option value="">Todos</option>
                    <option value="ATIVO" @selected(($filtros['status'] ?? null) === 'ATIVO')>Ativos</option>
                    <option value="INATIVO" @selected(($filtros['status'] ?? null) === 'INATIVO')>Inativos</option>
                </select>
            </div>
        </x-slot:filters>

        @include('admin.veiculos._table', [
            'veiculos' => $veiculos,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
