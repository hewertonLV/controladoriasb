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

    <x-admin.datatable
        title="Praças"
        subtitle="Cadastro de praças por unidade de negócio."
        table-id="pracas-datatable"
        root-id="pracas-table-root"
        print-title="Praças"
        entity-label="praças"
        entity-label-singular="praça"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'id_unidade_negocio',
            2 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1, 2], 'className' => 'text-nowrap'],
        ]"
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
        ])
    </x-admin.datatable>
@endsection
