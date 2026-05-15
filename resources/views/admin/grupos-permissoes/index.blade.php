@extends('layouts.app')

@section('title', 'Grupos de Permissões')
@section('page-title', 'Grupos de Permissões')

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-check-double-line me-1 align-middle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-1 align-middle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <x-admin.data-table
        title="Grupos de Permissões"
        subtitle="Gerencie os perfis de acesso do sistema."
        search-placeholder="Pesquisar por nome do grupo..."
        :endpoint="route('admin.grupos-permissoes.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'name'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="grupos-permissoes-table"
    >
        <x-slot:actions>
            @can('grupos-permissoes.criar')
                <a href="{{ route('admin.grupos-permissoes.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Novo Grupo
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.grupos-permissoes._table', [
            'roles' => $roles,
            'roleProgramador' => $roleProgramador,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
        ])
    </x-admin.data-table>
@endsection
