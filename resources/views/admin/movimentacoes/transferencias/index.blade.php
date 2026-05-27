@extends('layouts.app')

@section('title', 'Transferências')
@section('page-title', 'Movimentação — Transferência')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Transferências"
        subtitle="Saída na origem e entrada pendente no destino (versão ativa)."
        table-id="transferencias-movimentacao-datatable"
        root-id="transferencias-movimentacao-table-root"
        print-title="Movimentação — Transferências"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[0, 'desc']"
        :sort-column-map="[
            0 => 'data_movimentacao',
            1 => 'origem',
            2 => 'destino',
            3 => 'fruta',
            4 => 'status',
            5 => 'qtd_fruta_um',
            6 => 'qtd_fruta_kg',
            7 => 'frete',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [5, 6], 'className' => 'text-end'],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.transferencias.importar')
                <a href="{{ route('admin.movimentacoes.transferencias.importar') }}" class="btn btn-soft-primary btn-sm">
                    <i class="ri-file-excel-2-line me-1"></i> Importar transferências
                </a>
            @endcan
            @can('movimentacoes.transferencias.criar')
                <a href="{{ route('admin.movimentacoes.transferencias.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova transferência
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.transferencias._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
