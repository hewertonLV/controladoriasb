@extends('layouts.app')

@section('title', 'Compras')
@section('page-title', 'Movimentação — Compra')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Compras"
        subtitle="Movimentações da categoria compra (versão ativa)."
        table-id="compras-movimentacao-datatable"
        root-id="compras-movimentacao-table-root"
        print-title="Movimentação — Compras"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[2, 'desc']"
        :sort-column-map="[
            0 => 'numero_compra',
            1 => 'numero_nf',
            2 => 'data_movimentacao',
            3 => 'fornecedor',
            4 => 'unidade',
            5 => 'fruta',
            6 => 'qtd_fruta_um',
            7 => 'qtd_fruta_kg',
            8 => 'valor_nf_total',
            9 => 'frete',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [6, 7, 8], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.compras.criar')
                <a href="{{ route('admin.movimentacoes.compras.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova compra
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.compras._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
