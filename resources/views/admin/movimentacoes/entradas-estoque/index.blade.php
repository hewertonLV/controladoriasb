@extends('layouts.app')

@section('title', 'Entradas de estoque')
@section('page-title', 'Movimentação — Entrada de estoque')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Entradas de estoque"
        subtitle="Produção interna — aumenta saldo e recalcula preço médio."
        table-id="entradas-estoque-movimentacao-datatable"
        root-id="entradas-estoque-movimentacao-table-root"
        print-title="Movimentação — Entradas de estoque"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'unidade',
            2 => 'fruta',
            3 => 'qtd_fruta_um',
            4 => 'valor_nf_um',
            5 => 'valor_nf_total',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [3, 4, 5], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.entradas-estoque.criar')
                <a href="{{ route('admin.movimentacoes.entradas-estoque.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova entrada
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.entradas-estoque._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
