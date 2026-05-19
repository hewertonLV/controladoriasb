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

    <x-admin.datatable
        title="Fretes"
        subtitle="Cadastro de fretes (nome, valores, veículo e situação ABERTA/ENCERRADA)."
        table-id="fretes-datatable"
        root-id="fretes-table-root"
        print-title="Fretes"
        entity-label="fretes"
        entity-label-singular="frete"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'valor',
            3 => 'status_situacao',
            4 => 'valor_fruta_kg',
            5 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => 2, 'orderable' => false],
            ['targets' => [0, 1, 3, 4, 5], 'className' => 'text-nowrap'],
        ]"
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

        @include('admin.fretes._table', [
            'fretes' => $fretes,
        ])
    </x-admin.datatable>
@endsection
