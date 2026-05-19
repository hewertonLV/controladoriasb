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

    <x-admin.datatable
        title="Frutas"
        subtitle="Cadastro mestre de frutas (CIGAM, nome, unidade e kg). ICMS por estado no cadastro ou importação dedicada."
        table-id="frutas-datatable"
        root-id="frutas-table-root"
        print-title="Frutas"
        entity-label="frutas"
        entity-label-singular="fruta"
        :order="[1, 'asc']"
        :sort-column-map="[
            0 => 'id_cigam',
            1 => 'nome',
            2 => 'unidade_medicao',
            3 => 'kg_por_unidade_medicao',
            5 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => 4, 'orderable' => false],
            ['targets' => [0, 1, 2, 3, 5], 'className' => 'text-nowrap'],
        ]"
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
                    <i class="ri-file-excel-2-line me-1"></i> Importar frutas
                </a>
            @endcan
            @can('frutas.icms.visualizar')
                <a href="{{ route('admin.frutas.icms.index') }}" class="btn btn-soft-info">
                    <i class="ri-percent-line me-1"></i> ICMS
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
        ])
    </x-admin.datatable>
@endsection
