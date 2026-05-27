@extends('layouts.app')

@section('title', 'Descartes')
@section('page-title', 'Movimentação — Descarte')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Descartes"
        subtitle="Saídas por perda operacional com custo médio preservado (versão ativa)."
        table-id="descartes-movimentacao-datatable"
        root-id="descartes-movimentacao-table-root"
        print-title="Movimentação — Descartes"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'origem',
            2 => 'fruta',
            3 => 'categoria',
            4 => 'qtd_fruta_um',
            5 => 'qtd_fruta_kg',
            6 => 'valor',
            7 => 'status_registro',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [4, 5, 6], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.descartes.criar')
                <a href="{{ route('admin.movimentacoes.descartes.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Novo descarte
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.descartes._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
