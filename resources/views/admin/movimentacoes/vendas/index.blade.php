@extends('layouts.app')

@section('title', 'Vendas')
@section('page-title', 'Movimentação — Venda')

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao.pedidos-por-loja._card-estilos')

    @include('admin.movimentacoes._demandas-modulo-grid', [
        'demandas' => $demandasCards ?? [],
        'titulo' => 'Demandas pendentes',
    ])

    <x-admin.datatable
        title="Vendas"
        subtitle="Saídas comerciais/fiscais por item de fruta (versão ativa)."
        table-id="vendas-movimentacao-datatable"
        root-id="vendas-movimentacao-table-root"
        print-title="Movimentação — Vendas"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'numero_nf',
            2 => 'origem',
            3 => 'cliente',
            4 => 'fruta',
            5 => 'qtd_fruta_kg',
            6 => 'valor_nf_total',
            7 => 'resultado_movimentacao',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [5, 6, 7], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.vendas.importar')
                <a href="{{ route('admin.movimentacoes.vendas.importar') }}" class="btn btn-soft-primary btn-sm">
                    <i class="ri-file-excel-2-line me-1"></i> Importar NF de vendas
                </a>
            @endcan
            @can('movimentacoes.vendas.criar')
                <a href="{{ route('admin.movimentacoes.vendas.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova venda
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.vendas._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
