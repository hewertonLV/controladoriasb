@extends('layouts.app')

@section('title', 'Estoques')
@section('page-title', 'Estoques')

@section('content')
    <x-admin.flash-messages />

    @can('estoques.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="estoques-exportacao"
            table-root-id="estoques-table-root"
        />
    @endcan

    <x-admin.data-table
        title="Estoques consolidados"
        subtitle="Posição atual por unidade de negócio e fruta (quantidades em kg e unidade de medição da fruta)."
        search-placeholder="Pesquisar por unidade, fruta ou ID CIGAM..."
        :endpoint="route('admin.estoques.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'unidade'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="estoques-table"
    >
        <x-slot:actions>
            @can('estoques.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.estoques.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('estoques.importar')
                <a href="{{ route('admin.estoques.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('estoques.movimentar')
                <a href="{{ route('admin.estoques.movimentar') }}" class="btn btn-primary">
                    <i class="ri-exchange-line me-1"></i> Movimentar estoque
                </a>
            @endcan
        </x-slot:actions>

        <x-slot:filters>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="estoques-filtro-unidade">Unidade</label>
                <select id="estoques-filtro-unidade" name="id_unidade_negocio" class="form-select" data-table-filter>
                    <option value="">Todas</option>
                    @foreach ($unidadesFiltro as $u)
                        <option value="{{ $u->id }}" @selected(($filtros['id_unidade_negocio'] ?? null) === $u->id)>
                            {{ $u->nome }} ({{ $u->id_cigam }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="estoques-filtro-fruta">Fruta</label>
                <select id="estoques-filtro-fruta" name="id_fruta" class="form-select" data-table-filter>
                    <option value="">Todas</option>
                    @foreach ($frutasFiltro as $f)
                        <option value="{{ $f->id }}" @selected(($filtros['id_fruta'] ?? null) === $f->id)>
                            {{ $f->nome }} ({{ $f->id_cigam }})
                        </option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        @include('admin.estoques._table', [
            'estoques' => $estoques,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
