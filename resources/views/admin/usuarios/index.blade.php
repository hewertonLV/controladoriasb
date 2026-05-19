@extends('layouts.app')

@section('title', 'Usuários')
@section('page-title', 'Usuários')

@section('content')
    <x-admin.flash-messages />

    <x-admin.datatable
        title="Usuários"
        subtitle="Gerencie os usuários do sistema, grupos de permissão e unidades permitidas."
        table-id="usuarios-datatable"
        root-id="usuarios-table-root"
        print-title="Usuários"
        entity-label="usuários"
        entity-label-singular="usuário"
        :order="[0, 'asc']"
        :sort-column-map="[
            0 => 'name',
            1 => 'login',
            2 => 'email',
            5 => 'ativo',
            6 => 'must_change_password',
            7 => 'created_at',
        ]"
        :column-defs="[
            ['targets' => -1, 'orderable' => false, 'searchable' => false],
            ['targets' => [3, 4], 'orderable' => false],
            ['targets' => [0, 1, 2, 5, 6, 7], 'className' => 'text-nowrap'],
        ]"
    >
        <x-slot:actions>
            @can('usuarios.criar')
                <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
                    <i class="ri-user-add-line me-1"></i> Novo usuário
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.usuarios._table', [
            'users' => $users,
            'protectedEmail' => $protectedEmail,
        ])
    </x-admin.datatable>
@endsection
