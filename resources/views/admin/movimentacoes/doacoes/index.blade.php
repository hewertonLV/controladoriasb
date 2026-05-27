@extends('layouts.app')

@section('title', 'Doações')
@section('page-title', 'Movimentação — Doação')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Doações"
        subtitle="Saídas de estoque com custo médio preservado (versão ativa)."
        table-id="doacoes-movimentacao-datatable"
        root-id="doacoes-movimentacao-table-root"
        print-title="Movimentação — Doações"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'origem',
            2 => 'destino',
            3 => 'fruta',
            4 => 'qtd_fruta_um',
            5 => 'qtd_fruta_kg',
            6 => 'valor_baixa',
            7 => 'motivo',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [4, 5, 6], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.doacoes.criar')
                <a href="{{ route('admin.movimentacoes.doacoes.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova doação
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.doacoes._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
