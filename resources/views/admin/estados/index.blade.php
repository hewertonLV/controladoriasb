@extends('layouts.app')

@section('title', 'Estados')
@section('page-title', 'Estados')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Estados"
        subtitle="Cadastro de UFs para ICMS (nome, sigla e regras de negócio na descrição)."
        table-id="estados-datatable"
        root-id="estados-table-root"
        print-title="Estados"
        entity-label="estados"
        entity-label-singular="estado"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'abreviacao',
            4 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [2, 3], 'orderable' => false],
            ['targets' => [0, 1, 4], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('estados.importar')
                <a href="{{ route('admin.estados.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('estados.criar')
                <a href="{{ route('admin.estados.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo estado
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.estados._table', [
            'estados' => $estados,
        ])
    </x-admin.datatable>
@endsection
