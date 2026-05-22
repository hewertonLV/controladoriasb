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

        @php
            $sidebarUserName = auth()->user()?->name ?? 'Visitante';
            $sidebarUserEmail = auth()->user()?->email ?? '';
        @endphp

        <div class="sidenav-user">
            <div class="dropdown-center">
                <a class="topbar-link dropdown-toggle text-reset drop-arrow-none px-2 d-flex align-items-center justify-content-center"
                   data-bs-toggle="dropdown" data-bs-offset="0,19" type="button" aria-haspopup="false" aria-expanded="false">
                    <x-user-avatar :size="42" class="me-2 d-flex" alt="user-image" />
                    <span class="d-flex flex-column gap-1 sidebar-user-name min-w-0">
                        <h4 class="my-0 fw-bold fs-15 text-truncate sidebar-user-text" title="{{ $sidebarUserName }}">
                            {{ \Illuminate\Support\Str::limit($sidebarUserName, 24) }}
                        </h4>
                        <h6 class="my-0 text-truncate sidebar-user-text" title="{{ $sidebarUserEmail }}">
                            {{ \Illuminate\Support\Str::limit($sidebarUserEmail, 30) }}
                        </h6>
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

            @can(App\Enums\Permissions::OLHO_DE_FABIO_VISUALIZAR)
                <li class="side-nav-item">
                    <a href="{{ route('olho-de-fabio.index') }}"
                       class="side-nav-link {{ request()->routeIs('olho-de-fabio.*') ? 'active' : '' }}">
                        <span class="menu-icon"><i class="ri-eye-line"></i></span>
                        <span class="menu-text"> Olho de Fabio </span>
                    </a>
                </li>
            @endcan

            <li class="side-nav-item">
                <a href="{{ route('profile.edit') }}" class="side-nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ri-user-line"></i></span>
                    <span class="menu-text"> Meu Perfil </span>
                </a>
            </li>

            @canany(['empresas.visualizar', 'estados.visualizar', 'unidades-negocio.visualizar', 'fornecedores.visualizar', 'veiculos.visualizar', 'clientes.visualizar', 'grupos.visualizar', 'grupos-contrato.visualizar', 'frutas.visualizar', 'pracas.visualizar', 'estoques.visualizar'])
                <li class="side-nav-title mt-2">Cadastros</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarCadastros"
                       aria-expanded="{{ request()->routeIs('admin.empresas.*', 'admin.estados.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.grupos-contrato.*', 'admin.frutas.*', 'admin.pracas.*', 'admin.estoques.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarCadastros"
                       class="side-nav-link {{ request()->routeIs('admin.empresas.*', 'admin.estados.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.grupos-contrato.*', 'admin.frutas.*', 'admin.pracas.*', 'admin.estoques.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-building-line"></i></span>
                        <span class="menu-text"> Cadastros </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.empresas.*', 'admin.estados.*', 'admin.unidades-negocio.*', 'admin.fornecedores.*', 'admin.veiculos.*', 'admin.clientes.*', 'admin.grupos.*', 'admin.grupos-contrato.*', 'admin.frutas.*', 'admin.pracas.*', 'admin.estoques.*') ? 'show' : '' }}" id="sidebarCadastros">
                        <ul class="sub-menu">
                            @can('empresas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.empresas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.empresas.*') ? 'active' : '' }}">
                                        <span class="menu-text">Empresas</span>
                                    </a>
                                </li>
                            @endcan
                            @can('estados.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.estados.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.estados.*') ? 'active' : '' }}">
                                        <span class="menu-text">Estados</span>
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

                            @can('grupos-contrato.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.grupos-contrato.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.grupos-contrato.*') ? 'active' : '' }}">
                                        <span class="menu-text">Grupos de Contrato</span>
                                    </a>
                                </li>
                            @endcan

                            @can('frutas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.frutas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.frutas.index', 'admin.frutas.create', 'admin.frutas.edit', 'admin.frutas.importar', 'admin.frutas.historico') ? 'active' : '' }}">
                                        <span class="menu-text">Frutas</span>
                                    </a>
                                </li>
                            @endcan

                            @can('frutas.icms.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.frutas.icms.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.frutas.icms.*') ? 'active' : '' }}">
                                        <span class="menu-text">ICMS Frutas</span>
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

            @canany(['fretes.visualizar'])
                <li class="side-nav-title mt-2">Logística</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarLogistica"
                       aria-expanded="{{ request()->routeIs('admin.fretes.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarLogistica"
                       class="side-nav-link {{ request()->routeIs('admin.fretes.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-truck-line"></i></span>
                        <span class="menu-text"> Logística </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.fretes.*') ? 'show' : '' }}" id="sidebarLogistica">
                        <ul class="sub-menu">
                            @can('fretes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.fretes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.fretes.index', 'admin.fretes.create', 'admin.fretes.edit', 'admin.fretes.historico', 'admin.fretes.importar*', 'admin.fretes.exportacoes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Fretes</span>
                                    </a>
                                </li>
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.fretes.calendario') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.fretes.calendario*') ? 'active' : '' }}">
                                        <span class="menu-text">Calendário</span>
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
                'movimentacoes.transferencias.visualizar',
                'movimentacoes.transferencias.criar',
                'movimentacoes.doacoes.visualizar',
                'movimentacoes.doacoes.criar',
                'movimentacoes.entradas-estoque.visualizar',
                'movimentacoes.entradas-estoque.criar',
                'movimentacoes.descartes.visualizar',
                'movimentacoes.descartes.criar',
                'movimentacoes.vendas.visualizar',
                'movimentacoes.vendas.criar',
                'movimentacoes.devolucoes.visualizar',
                'movimentacoes.devolucoes.criar',
                'movimentacoes.conversoes-embalagem.visualizar',
                'movimentacoes.conversoes-embalagem.criar',
            ])
                <li class="side-nav-title mt-2">Movimentações</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse"
                       href="#sidebarMovimentacoes"
                       aria-expanded="{{ request()->routeIs('admin.movimentacoes.compras.*') || request()->routeIs('admin.movimentacoes.transferencias.*') || request()->routeIs('admin.movimentacoes.entradas-estoque.*') || request()->routeIs('admin.movimentacoes.doacoes.*') || request()->routeIs('admin.movimentacoes.descartes.*') || request()->routeIs('admin.movimentacoes.vendas.*') || request()->routeIs('admin.movimentacoes.devolucoes.*') || request()->routeIs('admin.movimentacoes.conversoes-embalagem.*') ? 'true' : 'false' }}"
                       aria-controls="sidebarMovimentacoes"
                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.compras.*') || request()->routeIs('admin.movimentacoes.transferencias.*') || request()->routeIs('admin.movimentacoes.entradas-estoque.*') || request()->routeIs('admin.movimentacoes.doacoes.*') || request()->routeIs('admin.movimentacoes.descartes.*') || request()->routeIs('admin.movimentacoes.vendas.*') || request()->routeIs('admin.movimentacoes.devolucoes.*') || request()->routeIs('admin.movimentacoes.conversoes-embalagem.*') ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ri-exchange-funds-line"></i></span>
                        <span class="menu-text"> Movimentações </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.movimentacoes.compras.*') || request()->routeIs('admin.movimentacoes.transferencias.*') || request()->routeIs('admin.movimentacoes.entradas-estoque.*') || request()->routeIs('admin.movimentacoes.doacoes.*') || request()->routeIs('admin.movimentacoes.descartes.*') || request()->routeIs('admin.movimentacoes.vendas.*') || request()->routeIs('admin.movimentacoes.devolucoes.*') || request()->routeIs('admin.movimentacoes.conversoes-embalagem.*') ? 'show' : '' }}" id="sidebarMovimentacoes">
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

                            @can('movimentacoes.transferencias.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.transferencias.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.transferencias.*') ? 'active' : '' }}">
                                        <span class="menu-text">Transferência</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.transferencias.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.transferencias.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.transferencias.*') ? 'active' : '' }}">
                                            <span class="menu-text">Transferência</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.entradas-estoque.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.entradas-estoque.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.entradas-estoque.*') ? 'active' : '' }}">
                                        <span class="menu-text">Entrada de estoque</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.entradas-estoque.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.entradas-estoque.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.entradas-estoque.*') ? 'active' : '' }}">
                                            <span class="menu-text">Entrada de estoque</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.doacoes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.doacoes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.doacoes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Doação</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.doacoes.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.doacoes.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.doacoes.*') ? 'active' : '' }}">
                                            <span class="menu-text">Doação</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.descartes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.descartes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.descartes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Descarte</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.descartes.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.descartes.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.descartes.*') ? 'active' : '' }}">
                                            <span class="menu-text">Descarte</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.vendas.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.vendas.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.vendas.*') ? 'active' : '' }}">
                                        <span class="menu-text">Venda</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.vendas.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.vendas.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.vendas.*') ? 'active' : '' }}">
                                            <span class="menu-text">Venda</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.devolucoes.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.devolucoes.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.devolucoes.*') ? 'active' : '' }}">
                                        <span class="menu-text">Devolução</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.devolucoes.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.devolucoes.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.devolucoes.*') ? 'active' : '' }}">
                                            <span class="menu-text">Devolução</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan

                            @can('movimentacoes.conversoes-embalagem.visualizar')
                                <li class="side-nav-item">
                                    <a href="{{ route('admin.movimentacoes.conversoes-embalagem.index') }}"
                                       class="side-nav-link {{ request()->routeIs('admin.movimentacoes.conversoes-embalagem.*') ? 'active' : '' }}">
                                        <span class="menu-text">Conversão de embalagem</span>
                                    </a>
                                </li>
                            @else
                                @can('movimentacoes.conversoes-embalagem.criar')
                                    <li class="side-nav-item">
                                        <a href="{{ route('admin.movimentacoes.conversoes-embalagem.create') }}"
                                           class="side-nav-link {{ request()->routeIs('admin.movimentacoes.conversoes-embalagem.*') ? 'active' : '' }}">
                                            <span class="menu-text">Conversão de embalagem</span>
                                        </a>
                                    </li>
                                @endcan
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany

            @can('relatorios.rentabilidade-loja.visualizar')
                <li class="side-nav-title mt-2">Relatórios</li>
                <li class="side-nav-item">
                    <a href="{{ route('admin.relatorios.rentabilidade-loja.index') }}"
                       class="side-nav-link {{ request()->routeIs('admin.relatorios.rentabilidade-loja.*') ? 'active' : '' }}">
                        <span class="menu-icon"><i class="ri-line-chart-line"></i></span>
                        <span class="menu-text">Rentabilidade por loja</span>
                    </a>
                </li>
            @endcan

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

        

        <div class="clearfix"></div>
    </div>
</div>
