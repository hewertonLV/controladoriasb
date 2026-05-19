@extends('layouts.app')

@section('title', 'Grupos de Permissões')
@section('page-title', 'Grupos de Permissões')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Grupos de Permissões"
        subtitle="Gerencie os perfis de acesso e permissões do sistema."
        table-id="grupos-permissoes-datatable"
        root-id="grupos-permissoes-table-root"
        print-title="Grupos de Permissões"
        entity-label="grupos de permissão"
        entity-label-singular="grupo de permissão"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'name',
            1 => 'guard_name',
            2 => 'permissions_count',
            3 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [0, 1, 2, 3], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('grupos-permissoes.criar')
                <a href="{{ route('admin.grupos-permissoes.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo grupo
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.grupos-permissoes._table', [
            'roles' => $roles,
            'roleProgramador' => $roleProgramador,
        ])
    </x-admin.datatable>
@endsection
