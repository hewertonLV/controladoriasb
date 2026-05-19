@extends('layouts.app')

@section('title', 'Grupos de Contrato')
@section('page-title', 'Grupos de Contrato')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Grupos de Contrato"
        subtitle="Controle de grupos contratuais, membros por competência e descontos mensais."
        table-id="grupos-contrato-datatable"
        root-id="grupos-contrato-table-root"
        print-title="Grupos de Contrato"
        entity-label="grupos de contrato"
        entity-label-singular="grupo de contrato"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'nome',
            1 => 'ativo',
            4 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [2, 3], 'orderable' => false],
            ['targets' => [0, 1, 4], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('grupos-contrato.criar')
                <a href="{{ route('admin.grupos-contrato.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo grupo de contrato
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.grupos-contrato._table', [
            'gruposContrato' => $gruposContrato,
        ])
    </x-admin.datatable>
@endsection
