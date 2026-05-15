@extends('layouts.app')

@section('title', 'Fretes')
@section('page-title', 'Fretes')

@section('content')
    <x-admin.flash-messages />

    @can('fretes.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="fretes-exportacao"
            table-root-id="fretes-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Fretes"
        subtitle="Cadastro de fretes (nome, valores, veículo e situação ABERTA/ENCERRADA)."
        search-placeholder="Pesquisar por nome, descrição, situação ou ID SBS do veículo..."
        :endpoint="route('admin.fretes.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="fretes-table"
    >
        <x-slot:actions>
            @can('fretes.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.fretes.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('fretes.importar')
                <a href="{{ route('admin.fretes.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('fretes.criar')
                <a href="{{ route('admin.fretes.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo frete
                </a>
            @endcan
        </x-slot:actions>

        <x-slot:filters>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="fretes-status-situacao">Situação</label>
                <select id="fretes-status-situacao" name="status_situacao" class="form-select" data-table-filter>
                    <option value="">Todas</option>
                    <option value="ABERTA" @selected(($filtros['status_situacao'] ?? null) === 'ABERTA')>Abertas</option>
                    <option value="ENCERRADA" @selected(($filtros['status_situacao'] ?? null) === 'ENCERRADA')>Encerradas</option>
                </select>
            </div>
        </x-slot:filters>

        @include('admin.fretes._table', [
            'fretes' => $fretes,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
