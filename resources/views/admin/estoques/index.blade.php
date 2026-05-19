@extends('layouts.app')

@section('title', 'Estoques')
@section('page-title', 'Estoques')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Estoques"
        subtitle="Selecione uma unidade de negócio para ver o estoque por fruta."
        table-id="estoques-unidades-datatable"
        root-id="estoques-table-root"
        print-title="Estoques por unidade"
        entity-label="unidades"
        entity-label-singular="unidade"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'id_cigam',
            2 => 'posicoes_count',
            3 => 'total_kg',
            4 => 'valor_total',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1, 2, 3, 4], 'className' => 'text-nowrap'],
        ]"
    >
        @include('admin.estoques._unidades_table', [
            'unidades' => $unidades,
        ])
    </x-admin.datatable>
@endsection
