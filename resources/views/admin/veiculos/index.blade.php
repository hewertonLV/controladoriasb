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

    <x-admin.datatable
        title="Veículos"
        subtitle="Cadastro de veículos (SBS, nome, tipo e unidade de negócio)."
        table-id="veiculos-datatable"
        root-id="veiculos-table-root"
        print-title="Veículos"
        entity-label="veículos"
        entity-label-singular="veículo"
        :order="[1, 'asc']"
        :sort-column-map="[
            0 => 'id_sbs',
            1 => 'nome',
            2 => 'tipo',
            3 => 'status',
            4 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1, 2, 3, 4], 'className' => 'text-nowrap'],
        ]"
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

        @include('admin.veiculos._table', [
            'veiculos' => $veiculos,
        ])
    </x-admin.datatable>
@endsection
