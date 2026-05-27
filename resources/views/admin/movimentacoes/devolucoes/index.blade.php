@extends('layouts.app')

@section('title', 'Devoluções')
@section('page-title', 'Movimentação — Devolução')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Devoluções"
        subtitle="Devoluções vinculadas a vendas originais."
        table-id="devolucoes-movimentacao-datatable"
        root-id="devolucoes-movimentacao-table-root"
        print-title="Movimentação — Devoluções"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'numero_nf_devolucao',
            2 => 'numero_nf_venda',
            3 => 'tipo_devolucao',
            4 => 'fruta',
            5 => 'qtd_fruta_kg',
            6 => 'valor_devolucao_total',
            7 => 'resultado_devolucao',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [5, 6, 7], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.devolucoes.criar')
                <a href="{{ route('admin.movimentacoes.devolucoes.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova devolução
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.devolucoes._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
