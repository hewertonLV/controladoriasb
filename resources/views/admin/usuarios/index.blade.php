@extends('layouts.app')

@section('title', 'Usuários')
@section('page-title', 'Usuários')

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

    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="ri-information-line me-1 align-middle"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <x-admin.data-table
        title="Usuários"
        subtitle="Gerencie os usuários do sistema."
        search-placeholder="Pesquisar por nome, login, e-mail ou grupo..."
        :endpoint="route('admin.usuarios.index')"
        :current-search="$filtros['search'] ?? ''"
        :current-per-page="$filtros['per_page'] ?? 20"
        :current-sort="$filtros['sort'] ?? 'name'"
        :current-direction="$filtros['direction'] ?? 'asc'"
        :per-page-options="$perPageOptions"
        container-id="usuarios-table"
    >
        <x-slot:actions>
            @can('usuarios.criar')
                <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
                    <i class="ri-user-add-line me-1"></i> Novo Usuário
                </a>
            @endcan
        </x-slot:actions>

        @include('admin.usuarios._table', [
            'users' => $users,
            'filtros' => $filtros,
            'total' => $total,
            'exibindo' => $exibindo,
            'protectedEmail' => $protectedEmail,
        ])
    </x-admin.data-table>
@endsection
