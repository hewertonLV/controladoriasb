@extends('layouts.app')

@section('title', 'Meu Perfil')
@section('page-title', 'Meu Perfil')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" crossorigin="anonymous">
@endpush

@section('content')
    <div class="row">
        <div class="col-xl-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">Dados do perfil</h4>
                    <p class="text-muted mb-0">Atualize sua foto, nome e e-mail.</p>
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

    @include('profile.partials.avatar-crop-modal')
@endsection

@push('scripts')
    @php
        $avatarConfig = config('profile.avatar');
    @endphp
    <script>
        window.__PROFILE_AVATAR__ = {
            maxBytes: {{ (int) $avatarConfig['max_kb'] * 1024 }},
            outputSize: {{ (int) $avatarConfig['output_size'] }},
            maxQuality: {{ (float) $avatarConfig['max_quality'] }},
            minQuality: {{ (float) $avatarConfig['min_quality'] }},
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/js/profile-avatar-crop.js') }}"></script>
@endpush
