<div class="sidenav-menu">

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

    <button class="button-sm-hover">
        <i class="ri-circle-line align-middle"></i>
    </button>

    <button class="button-close-fullsidebar">
        <i class="ri-close-line align-middle"></i>
    </button>

    <div data-simplebar>

        <div class="sidenav-user">
            <div class="dropdown-center">
                <a class="topbar-link dropdown-toggle text-reset drop-arrow-none px-2 d-flex align-items-center justify-content-center"
                   data-bs-toggle="dropdown" data-bs-offset="0,19" type="button" aria-haspopup="false" aria-expanded="false">
                    <img src="{{ asset('assets/images/users/avatar-1.jpg') }}" width="42" class="rounded-circle me-2 d-flex" alt="user-image">
                    <span class="d-flex flex-column gap-1 sidebar-user-name">
                        <h4 class="my-0 fw-bold fs-15">{{ auth()->user()?->name ?? 'Visitante' }}</h4>
                        <h6 class="my-0 text-truncate">{{ auth()->user()?->email }}</h6>
                    </span>
                    <i class="ri-arrow-down-s-line d-block sidebar-user-arrow align-middle ms-2"></i>
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

        <ul class="side-nav">
            <li class="side-nav-title">Navegação</li>

            <li class="side-nav-item">
                <a href="{{ route('dashboard') }}" class="side-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ri-dashboard-3-line"></i></span>
                    <span class="menu-text"> Dashboard </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('profile.edit') }}" class="side-nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ri-user-line"></i></span>
                    <span class="menu-text"> Meu Perfil </span>
                </a>
            </li>

            @canany(['empresas.visualizar', 'unidades-negocio.visualizar', 'fornecedores.visualizar', 'veiculos.visualizar', 'clientes.visualizar', 'grupos.visualizar', 'frutas.visualizar', 'fretes.visualizar', 'pracas.visualizar', 'estoques.visualizar'])
                <li class="side-nav-title mt-2">Cadastros</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarCadastros"
                       aria-expanded="{{ request()->routeIs('admin.empresas.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.frutas.*', 'admin.fretes.*', 'admin.pracas.*', 'admin.estoques.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarCadastros"
                       class="side-nav-link {{ request()->routeIs('admin.empresas.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.frutas.*', 'admin.fretes.*', 'admin.pracas.*', 'admin.estoques.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-building-line"></i></span>
                        <span class="menu-text"> Cadastros </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.empresas.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.frutas.*', 'admin.fretes.*', 'admin.pracas.*', 'admin.estoques.*') ? 'show' : '' }}" id="sidebarCadastros">
                        <ul class="sub-menu">
                            @can('empresas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.empresas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.empresas.*') ? 'active' : '' }}">
                                        <span class="menu-text">Empresas</span>
                                    </a>
                                </li>
                            @endcan
                            @can('unidades-negocio.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.unidades-negocio.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.unidades-negocio.*') ? 'active' : '' }}">
                                        <span class="menu-text">Unidades de Negócio</span>
                                    </a>
                                </li>
                            @endcan
                            @can('fornecedores.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.fornecedores.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.fornecedores.*') ? 'active' : '' }}">
                                        <span class="menu-text">Fornecedores</span>
                                    </a>
                                </li>
                            @endcan

                            @can('veiculos.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.veiculos.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.veiculos.*') ? 'active' : '' }}">
                                        <span class="menu-text">Veículos</span>
                                    </a>
                                </li>
                            @endcan

                            @can('fretes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.fretes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.fretes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Fretes</span>
                                    </a>
                                </li>
                            @endcan

                            @can('clientes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.clientes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.clientes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Clientes</span>
                                    </a>
                                </li>
                            @endcan

                            @can('grupos.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.grupos.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.grupos.*') ? 'active' : '' }}">
                                        <span class="menu-text">Grupos</span>
                                    </a>
                                </li>
                            @endcan

                            @can('frutas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.frutas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.frutas.*') ? 'active' : '' }}">
                                        <span class="menu-text">Frutas</span>
                                    </a>
                                </li>
                            @endcan

                            @can('estoques.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.estoques.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.estoques.*') ? 'active' : '' }}">
                                        <span class="menu-text">Estoques</span>
                                    </a>
                                </li>
                            @endcan

                            @can('pracas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.pracas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.pracas.*') ? 'active' : '' }}">
                                        <span class="menu-text">Praças</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany([
                'movimentacoes.compras.visualizar',
                'movimentacoes.compras.criar',
            ])
                <li class="side-nav-title mt-2">Movimentações</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarMovimentacoes"
                       aria-expanded="{{ request()->routeIs('admin.movimentacoes.compras.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarMovimentacoes"
                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.compras.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-exchange-funds-line"></i></span>
                        <span class="menu-text"> Movimentações </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.movimentacoes.compras.*') ? 'show' : '' }}" id="sidebarMovimentacoes">
                        <ul class="sub-menu">
                            @can('movimentacoes.compras.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.compras.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.compras.*') ? 'active' : '' }}">
                                        <span class="menu-text">Compra</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.compras.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.compras.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.compras.*') ? 'active' : '' }}">
                                            <span class="menu-text">Compra</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(['usuarios.visualizar', 'grupos-permissoes.visualizar'])
                <li class="side-nav-title mt-2">Administração</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarAdministracao"
                       aria-expanded="{{ request()->routeIs('admin.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarAdministracao"
                       class="side-nav-link {{ request()->routeIs('admin.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-shield-user-line"></i></span>
                        <span class="menu-text"> Administração </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.*') ? 'show' : '' }}" id="sidebarAdministracao">
                        <ul class="sub-menu">
                            @can('usuarios.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.usuarios.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                                        <span class="menu-text">Usuários</span>
                                    </a>
                                </li>
                            @endcan
                            @can('grupos-permissoes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.grupos-permissoes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.grupos-permissoes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Grupos de Permissões</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany
        </ul>

        <div class="help-box text-center">
            <h5 class="fw-semibold fs-16">{{ config('app.name') }}</h5>
            <p class="mb-3 opacity-75">Sistema em desenvolvimento</p>
        </div>

        <div class="clearfix"></div>
    </div>
</div>
