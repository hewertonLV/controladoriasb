@extends('layouts.app')

@section('title', 'Unidades de Negócio')
@section('page-title', 'Unidades de Negócio')

@section('content')
    <x-admin.flash-messages />

    @can('unidades-negocio.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="unidades-negocio-exportacao"
            table-root-id="unidades-negocio-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Unidades de Negócio FACIGAM 2"
        subtitle="Cadastro mestre de unidades de negócio (CIGAM, estado ICMS, razão social, nome, CPF/CNPJ, custo operacional e flag de estoque)."
        search-placeholder="Pesquisar por ID CIGAM, razão social, nome, CPF/CNPJ ou estado..."
        :endpoint="route('admin.unidades-negocio.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="unidades-negocio-table"
    >
        <x-slot:actions>
            @can('unidades-negocio.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.unidades-negocio.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('unidades-negocio.importar')
                <a href="{{ route('admin.unidades-negocio.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('unidades-negocio.criar')
                <a href="{{ route('admin.unidades-negocio.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Nova unidade
                </a>
            @endcan
        </x-slot:actions>

        <x-slot:filters>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1" for="unidades-negocio-estado">Estado (ICMS)</label>
                <select id="unidades-negocio-estado" name="id_estado" class="form-select" data-table-filter>
                    <option value="">Todos</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}" @selected((string) ($filtros['id_estado'] ?? '') === (string) $estado->id)>{{ $estado->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1" for="unidades-negocio-status">Status</label>
                <select id="unidades-negocio-status" name="status" class="form-select" data-table-filter>
                    <option value="">Todos</option>
                    <option value="1" @selected(($filtros['status'] ?? null) === '1')>Ativas</option>
                    <option value="0" @selected(($filtros['status'] ?? null) === '0')>Inativas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1" for="unidades-negocio-possui-estoque">Estoque</label>
                <select id="unidades-negocio-possui-estoque" name="possui_estoque" class="form-select" data-table-filter>
                    <option value="">Todos</option>
                    <option value="1" @selected(($filtros['possui_estoque'] ?? null) === '1')>Com estoque</option>
                    <option value="0" @selected(($filtros['possui_estoque'] ?? null) === '0')>Sem estoque</option>
                </select>
            </div>
        </x-slot:filters>

        @include('admin.unidades-negocio._table', [
            'unidadesNegocio' => $unidadesNegocio,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
            'estados' => $estados,
        ])
    </x-admin.data-table>
@endsection
