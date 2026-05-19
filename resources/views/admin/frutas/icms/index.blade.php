@extends('layouts.app')

@section('title', 'ICMS de Frutas')
@section('page-title', 'ICMS de Frutas')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="ICMS de Frutas"
        subtitle="Valores de ICMS por fruta e estado (compra nacional/externa e venda importada/nacional)."
        table-id="frutas-icms-datatable"
        root-id="frutas-icms-table-root"
        print-title="ICMS de Frutas"
        entity-label="registros"
        entity-label-singular="registro"
        :order="[0, 'asc']"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
        ]"
    >
        <x-slot:actions>
            @can('frutas.icms.importar')
                <a href="{{ route('admin.frutas.icms.importar') }}" class="btn btn-soft-success">
                    <i class="ri-file-excel-2-line me-1"></i> Importar Excel
                </a>
            @endcan
            @can('frutas.icms.criar')
                <a href="{{ route('admin.frutas.icms.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo ICMS
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.frutas.icms._table', [
            'registros' => $registros,
            'saidas' => $saidas,
        ])
    </x-admin.datatable>
@endsection
