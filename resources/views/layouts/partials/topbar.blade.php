<div class="color-line"></div>

<header class="app-topbar">
    <div class="page-container topbar-menu">
        <div class="d-flex align-items-center gap-2">

            <a href="{{ route('dashboard') }}" class="logo">
                <span class="logo-light">
                    <span class="logo-lg"><img src="{{ asset('assets/images/logo.png') }}" alt="logo"></span>
                    <span class="logo-sm"><img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo"></span>
                </span>

                <span class="logo-dark">
                    <span class="logo-lg"><img src="{{ asset('assets/images/logo-dark.png') }}" alt="dark logo"></span>
                    <span class="logo-sm"><img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo"></span>
                </span>
            </a>

            <button class="sidenav-toggle-button px-2">
                <i class="ri-menu-5-line fs-24"></i>
            </button>

            <button class="topnav-toggle-button px-2" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                <i class="ri-menu-5-line fs-24"></i>
            </button>

            <div class="topbar-item d-none d-md-flex">
                <div>
                    <h4 class="page-title fs-18 fw-bold mb-0">@yield('page-title', 'Bem-vindo!')</h4>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">

            <div class="topbar-item d-flex d-xl-none">
                <button class="topbar-link" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                    <i class="ri-search-line fs-22"></i>
                </button>
            </div>

            <div class="topbar-search d-none d-xl-flex gap-2 me-2 align-items-center"
                 data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                <i class="ri-search-line fs-18"></i>
                <span class="me-2">Pesquisar...</span>
            </div>

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

<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-transparent">
            <form>
                <div class="card mb-1">
                    <div class="px-3 py-2 d-flex flex-row align-items-center" id="top-search">
                        <i class="ri-search-line fs-22"></i>
                        <input type="search" class="form-control border-0" id="search-modal-input"
                               placeholder="Pesquisar...">
                        <button type="submit" class="btn p-0" data-bs-dismiss="modal" aria-label="Close">[esc]</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
