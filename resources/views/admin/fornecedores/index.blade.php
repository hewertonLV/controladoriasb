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

    <x-admin.data-table
        title="Fornecedores"
        subtitle="Cadastro mestre de fornecedores (código CIGAM, estado ICMS, razão social, fantasia e CPF/CNPJ)."
        search-placeholder="Pesquisar por ID CIGAM, razão social, fantasia, CPF/CNPJ ou estado..."
        :endpoint="route('admin.fornecedores.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'razao_social'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="fornecedores-table"
    >
        <x-slot:actions>
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
        </x-slot:actions>

        <x-slot:filters>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1" for="fornecedores-estado">Estado (ICMS)</label>
                <select id="fornecedores-estado" name="id_estado" class="form-select" data-table-filter>
                    <option value="">Todos</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}" @selected((string) ($filtros['id_estado'] ?? '') === (string) $estado->id)>{{ $estado->nome }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        @include('admin.fornecedores._table', [
            'fornecedores' => $fornecedores,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
            'estados' => $estados,
        ])
    </x-admin.data-table>
@endsection
