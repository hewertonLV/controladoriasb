<div class="color-line"></div>

<header class="app-topbar modulos-topbar">
    <div class="page-container topbar-menu">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('modulos.index') }}" class="logo">
                <span class="logo-light">
                    <span class="logo-lg"><img src="{{ asset('assets/images/logo.png') }}" alt="logo"></span>
                    <span class="logo-sm"><img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo"></span>
                </span>

                <span class="logo-dark">
                    <span class="logo-lg"><img src="{{ asset('assets/images/logo-dark.png') }}" alt="dark logo"></span>
                    <span class="logo-sm"><img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo"></span>
                </span>
            </a>

            <div class="topbar-item d-none d-md-flex">
                <div>
                    <h4 class="page-title fs-18 fw-bold mb-0">@yield('page-title', 'Módulos')</h4>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
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
