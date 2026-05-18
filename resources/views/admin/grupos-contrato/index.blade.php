@extends('layouts.app')

@section('title', 'Grupos de Contrato')
@section('page-title', 'Grupos de Contrato')

@section('content')
    <x-admin.flash-messages />

    <x-admin.data-table
        title="Grupos de Contrato"
        subtitle="Controle de grupos contratuais, membros por competência e descontos mensais."
        search-placeholder="Pesquisar por nome ou descrição..."
        :endpoint="route('admin.grupos-contrato.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'nome'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="grupos-contrato-table"
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
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
