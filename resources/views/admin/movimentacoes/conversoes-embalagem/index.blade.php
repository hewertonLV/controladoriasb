@extends('layouts.app')

@section('title', 'Conversões de embalagem')
@section('page-title', 'Movimentação — Conversão de embalagem')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Conversões de embalagem"
        subtitle="Movimentações de conversão entre embalagens/frutas com registro de perda."
        table-id="conversoes-embalagem-movimentacao-datatable"
        root-id="conversoes-embalagem-movimentacao-table-root"
        print-title="Movimentação — Conversões de embalagem"
        entity-label="movimentações"
        entity-label-singular="movimentação"
        :order="[5, 'desc']"
        :sort-column-map="[
            0 => 'id',
            1 => 'unidade',
            2 => 'origem',
            3 => 'destino',
            4 => 'perda',
            5 => 'data_movimentacao',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
        ]"
    >
        <x-slot:actions>
            @can('movimentacoes.conversoes-embalagem.criar')
                <a href="{{ route('admin.movimentacoes.conversoes-embalagem.create') }}" class="btn btn-primary btn-sm">
                    <i class="ri-add-line me-1"></i> Nova conversão
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.movimentacoes.conversoes-embalagem._table', [
            'movimentacoes' => $movimentacoes,
        ])
    </x-admin.datatable>
@endsection
