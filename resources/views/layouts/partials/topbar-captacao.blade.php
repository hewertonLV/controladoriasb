@php
    use App\Enums\AppModulo;

    $moduloAtivoTopbar = AppModulo::tryFromSession();
    $pageTitle = trim($__env->yieldContent('page-title')) ?: ($moduloAtivoTopbar?->label() ?? 'Captação');
@endphp

<header class="app-topbar topbar-modulo-captacao">
    <div class="page-container topbar-menu topbar-menu-modulo-captacao">
        <div class="topbar-captacao-start d-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('modulos.index') }}" class="btn btn-sm btn-light">
                <i class="ri-apps-line me-1"></i>
                Módulos
            </a>
            @can('captacao.lote.visualizar')
                <button type="button"
                        class="btn btn-sm btn-success"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-criar-captacao">
                    <i class="ri-add-line me-1"></i>
                    Criar Captação
                </button>
            @endcan
        </div>

        <div class="topbar-captacao-center px-2 min-w-0">
            <h4 class="page-title fs-18 fw-bold mb-0 text-truncate text-center">{{ $pageTitle }}</h4>
        </div>

        <div class="topbar-captacao-end d-flex align-items-center gap-2 justify-content-end flex-shrink-0">
            <div class="topbar-item d-none d-sm-flex">
                <button class="topbar-link" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" type="button">
                    <i class="ri-settings-4-line fs-22"></i>
                </button>
            </div>

            <div class="topbar-item d-none d-sm-flex">
                <button class="topbar-link" id="light-dark-mode" type="button">
                    <i class="ri-moon-line fs-22"></i>
                </button>
            </div>

            <div class="topbar-item nav-user">
                <div class="dropdown">
                    <a class="topbar-link dropdown-toggle drop-arrow-none px-2" data-bs-toggle="dropdown"
                       data-bs-offset="0,25" type="button" aria-haspopup="false" aria-expanded="false">
                        <x-user-avatar :size="32" class="me-lg-2 d-flex" alt="user-image" />
                        <span class="d-lg-flex flex-column gap-1 d-none">
                            <h5 class="my-0">{{ auth()->user()?->name ?? 'Conta' }}</h5>
                        </span>
                        <i class="ri-arrow-down-s-line d-none d-lg-block align-middle ms-2"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">Bem-vindo!</h6>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="dropdown-item">
                            <i class="ri-account-circle-line me-1 fs-16 align-middle"></i>
                            <span class="align-middle">Meu Perfil</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item fw-semibold text-danger">
                                <i class="ri-logout-box-line me-1 fs-16 align-middle"></i>
                                <span class="align-middle">Sair</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

@can('captacao.lote.visualizar')
    @include('admin.captacao._modal-criar-captacao', [
        'carteiras' => $carteirasAbrirCaptacao ?? collect(),
        'dataReferencia' => $dataReferenciaAbrirCaptacao ?? now()->toDateString(),
    ])
@endcan

@include('admin.captacao._search-select-scripts')
