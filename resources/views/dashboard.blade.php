@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <iconify-icon icon="solar:rocket-bold-duotone" class="fs-24 me-2"></iconify-icon>
        <div>
            <strong>{{ config('app.name') }}</strong> — base do sistema configurada com sucesso. Pronto para começar a programar a lógica.
        </div>
    </div>

    <div class="row row-cols-xxl-4 row-cols-md-2 row-cols-1">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 justify-content-between">
                        <div>
                            <h5 class="text-muted fs-13 fw-bold text-uppercase">Total de Registros</h5>
                            <h3 class="my-2 py-1 fw-bold">0</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-success me-1"><i class="ri-arrow-left-up-box-line"></i> 0%</span>
                                <span class="text-nowrap">desde o último mês</span>
                            </p>
                        </div>
                        <div class="avatar-xl flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-42">
                                <iconify-icon icon="solar:bill-list-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 justify-content-between">
                        <div>
                            <h5 class="text-muted fs-13 fw-bold text-uppercase">Usuários Ativos</h5>
                            <h3 class="my-2 py-1 fw-bold">0</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-success me-1"><i class="ri-arrow-left-up-box-line"></i> 0%</span>
                                <span class="text-nowrap">desde o último mês</span>
                            </p>
                        </div>
                        <div class="avatar-xl flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-42">
                                <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 justify-content-between">
                        <div>
                            <h5 class="text-muted fs-13 fw-bold text-uppercase">Novos Cadastros</h5>
                            <h3 class="my-2 py-1 fw-bold">0</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-success me-1"><i class="ri-arrow-left-up-box-line"></i> 0%</span>
                                <span class="text-nowrap">desde o último mês</span>
                            </p>
                        </div>
                        <div class="avatar-xl flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-42">
                                <iconify-icon icon="solar:user-plus-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 justify-content-between">
                        <div>
                            <h5 class="text-muted fs-13 fw-bold text-uppercase">Satisfação</h5>
                            <h3 class="my-2 py-1 fw-bold">0%</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-success me-1"><i class="ri-arrow-left-up-box-line"></i> 0%</span>
                                <span class="text-nowrap">desde o último mês</span>
                            </p>
                        </div>
                        <div class="avatar-xl flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-42">
                                <iconify-icon icon="solar:sticker-smile-circle-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">Próximos passos</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">A base do sistema está pronta. A partir daqui podemos começar a programar a lógica do projeto.</p>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent px-0">
                            <i class="ri-check-double-line text-success me-2"></i>
                            Laravel {{ app()->version() }} instalado
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            <i class="ri-check-double-line text-success me-2"></i>
                            Banco MySQL configurado via Docker
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            <i class="ri-check-double-line text-success me-2"></i>
                            Tema Highdmin implantado como layout principal
                        </li>
                        <li class="list-group-item bg-transparent px-0">
                            <i class="ri-time-line text-warning me-2"></i>
                            Modelar entidades, migrations, controllers e rotas
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
