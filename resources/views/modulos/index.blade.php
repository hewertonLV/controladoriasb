@extends('layouts.modulos')

@section('page-title', 'Módulos')

@section('content')
    <div class="row justify-content-center py-4">
        <div class="col-12 col-xl-10">
            <div class="text-center mb-4">
                <h2 class="fw-bold mb-2">Escolha um módulo</h2>
                <p class="text-muted mb-0">
                    Selecione a área de trabalho. Cada módulo abre com a navegação adequada ao seu perfil.
                </p>
            </div>

            @if ($semModulos ?? false)
                <div class="alert alert-warning text-center mb-0">
                    Nenhum módulo foi liberado para o seu usuário. Solicite ao administrador as permissões necessárias.
                </div>
            @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
                @foreach ($modulos as $card)
                    @include('modulos._card', ['card' => $card])
                @endforeach
            </div>
            @endif
        </div>
    </div>
@endsection
