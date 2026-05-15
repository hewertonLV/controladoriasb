@extends('layouts.app')

@section('title', 'Meu Perfil')
@section('page-title', 'Meu Perfil')

@section('content')
    <div class="row">
        <div class="col-xl-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">Dados do perfil</h4>
                    <p class="text-muted mb-0">Atualize seu nome e e-mail.</p>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">Alterar senha</h4>
                    <p class="text-muted mb-0">Use uma senha longa e segura.</p>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
@endsection
