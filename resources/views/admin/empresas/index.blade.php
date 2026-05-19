@extends('layouts.app')

@section('title', 'Empresas')
@section('page-title', 'Empresas')

@section('content')
    <x-admin.flash-messages />

    @can('empresas.exportar-pdf')
        <x-admin.exportacao-pdf-async
            queue="empresas-exportacao"
            table-root-id="empresas-table-root"
        />
    @endcan

    <x-admin.datatable
        title="Hub corporativo"
        subtitle="Visão unificada de clientes, fornecedores e unidades de negócio. Os dados cadastrais permanecem nos respectivos módulos."
        table-id="empresas-datatable"
        root-id="empresas-table-root"
        print-title="Hub corporativo — Empresas"
        entity-label="registros"
        entity-label-singular="registro"
        :order="[2, 'asc']"
        :sort-column-map="[
            0 => 'tipo_registro',
            1 => 'id_cigam',
            2 => 'nome_exibicao',
            3 => 'documento',
            4 => 'unidade_referencia',
            5 => 'tipo_pessoa',
            6 => 'status',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1, 3, 4, 5, 6], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('empresas.exportar-pdf')
                <button type="button"
                        class="btn btn-soft-danger"
                        id="btn-gerar-pdf"
                        data-pdf-iniciar-url="{{ route('admin.empresas.exportacoes.pdf.iniciar') }}">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-gerar-pdf" role="status" aria-hidden="true"></span>
                    <i class="ri-file-pdf-2-line me-1"></i> Gerar PDF
                </button>
            @endcan
            @can('empresas.importar')
                <a href="{{ route('admin.empresas.importar') }}" class="btn btn-soft-success">
                    <i class="ri-information-line me-1"></i> Sobre importação
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.empresas._table', [
            'empresas' => $empresas,
        ])
    </x-admin.datatable>
@endsection
