<div class="offcanvas offcanvas-end" tabindex="-1" id="theme-settings-offcanvas">
    <div class="d-flex align-items-center gap-2 px-3 py-3 offcanvas-header border-bottom border-dashed">
        <h5 class="flex-grow-1 mb-0">Configurações do Tema</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-0 h-100" data-simplebar>
        <div class="p-3 border-bottom border-dashed">
            <h5 class="mb-3 fs-16 fw-bold">Esquema de cores</h5>
            <div class="row">
                <div class="col-4">
                    <div class="form-check card-radio">
                        <input class="form-check-input" type="radio" name="data-bs-theme" id="layout-color-light" value="light">
                        <label class="form-check-label p-3 w-100 d-flex justify-content-center align-items-center" for="layout-color-light">
                            <iconify-icon icon="solar:sun-bold-duotone" class="fs-32 text-muted"></iconify-icon>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Claro</h5>
                </div>
                <div class="col-4">
                    <div class="form-check card-radio">
                        <input class="form-check-input" type="radio" name="data-bs-theme" id="layout-color-dark" value="dark">
                        <label class="form-check-label p-3 w-100 d-flex justify-content-center align-items-center" for="layout-color-dark">
                            <iconify-icon icon="solar:cloud-sun-2-bold-duotone" class="fs-32 text-muted"></iconify-icon>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Escuro</h5>
                </div>
            </div>
        </div>

        <div class="p-3 border-bottom border-dashed">
            <h5 class="mb-3 fs-16 fw-bold">Cor do Topbar</h5>
            <div class="row">
                <div class="col-3">
                    <div class="form-check card-radio">
                        <input class="form-check-input" type="radio" name="data-topbar-color" id="topbar-color-light" value="light">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="topbar-color-light">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-white"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Claro</h5>
                </div>
                <div class="col-3">
                    <div class="form-check card-radio">
                        <input class="form-check-input" type="radio" name="data-topbar-color" id="topbar-color-dark" value="dark">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="topbar-color-dark">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-dark"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Escuro</h5>
                </div>
                <div class="col-3">
                    <div class="form-check card-radio">
                        <input class="form-check-input" type="radio" name="data-topbar-color" id="topbar-color-brand" value="brand">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="topbar-color-brand">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-primary"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Marca</h5>
                </div>
            </div>
        </div>

        <div class="p-3 border-bottom border-dashed">
            <h5 class="mb-3 fs-16 fw-bold">Cor do Menu</h5>
            <div class="row">
                <div class="col-3">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-menu-color" id="sidenav-color-light" value="light">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="sidenav-color-light">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-white"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Claro</h5>
                </div>
                <div class="col-3" style="--ct-dark-rgb: 64,73,84;">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-menu-color" id="sidenav-color-dark" value="dark">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="sidenav-color-dark">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-dark"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Escuro</h5>
                </div>
                <div class="col-3">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-menu-color" id="sidenav-color-brand" value="brand">
                        <label class="form-check-label p-0 avatar-lg w-100 bg-light" for="sidenav-color-brand">
                            <span class="d-flex align-items-center justify-content-center h-100">
                                <span class="p-2 d-inline-flex shadow rounded-circle bg-primary"></span>
                            </span>
                        </label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Marca</h5>
                </div>
            </div>
        </div>

        <div class="p-3 border-bottom border-dashed">
            <h5 class="mb-3 fs-16 fw-bold">Tamanho do Sidebar</h5>
            <div class="row">
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-default" value="default">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-default"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Padrão</h5>
                </div>
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-compact" value="compact">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-compact"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Compacto</h5>
                </div>
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-small" value="condensed">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-small"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Condensado</h5>
                </div>
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-small-hover" value="sm-hover">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-small-hover"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Hover</h5>
                </div>
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-full" value="full">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-full"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Completo</h5>
                </div>
                <div class="col-4">
                    <div class="form-check sidebar-setting card-radio">
                        <input class="form-check-input" type="radio" name="data-sidenav-size" id="sidenav-size-fullscreen" value="fullscreen">
                        <label class="form-check-label p-0 avatar-xl w-100" for="sidenav-size-fullscreen"></label>
                    </div>
                    <h5 class="fs-14 text-center text-muted mt-2">Oculto</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 px-3 py-2 offcanvas-header border-top border-dashed">
        <button type="button" class="btn w-100 btn-soft-danger" id="reset-layout">Restaurar Padrão</button>
    </div>
</div>
