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

    <x-admin.datatable
        title="Grupos"
        subtitle="Cadastro de grupos de clientes (nome único, maiúsculas)."
        table-id="grupos-datatable"
        root-id="grupos-table-root"
        print-title="Grupos"
        entity-label="grupos"
        entity-label-singular="grupo"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1], 'className' => 'text-nowrap'],
        ]"
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
        ])
    </x-admin.datatable>
@endsection
