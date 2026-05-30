<div class="card mb-3 d-none" id="card-progresso">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h5 class="header-title mb-0" id="progresso-status-titulo">Analisando planilha...</h5>
                <p class="text-muted mb-0 small" id="progresso-arquivo">&nbsp;</p>
            </div>
            <div class="ms-auto fs-22 fw-bold text-primary" id="progresso-percentual">0%</div>
        </div>
        <div class="progress" style="height: 22px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 id="progresso-barra" role="progressbar" style="width: 0%">0%</div>
        </div>
        <div class="text-muted small mt-2" id="progresso-texto">Aguardando processamento...</div>
        <div class="row g-2 mt-3 text-center">
            <div class="col-4">
                <div class="border rounded p-2">
                    <div class="fs-20 fw-bold text-success" id="progresso-novas">0</div>
                    <div class="text-muted small">Novas lojas</div>
                </div>
            </div>
            <div class="col-4">
                <div class="border rounded p-2">
                    <div class="fs-20 fw-bold text-secondary" id="progresso-sem-alteracoes">0</div>
                    <div class="text-muted small">Já na carteira</div>
                </div>
            </div>
            <div class="col-4">
                <div class="border rounded p-2">
                    <div class="fs-20 fw-bold text-danger" id="progresso-erros">0</div>
                    <div class="text-muted small">Erros</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="resultado" class="d-none">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted me-2">Seleção:</span>
            <button type="button" class="btn btn-sm btn-soft-primary" id="btn-selecionar-tudo">Selecionar todos</button>
            <button type="button" class="btn btn-sm btn-soft-secondary" id="btn-desmarcar-tudo">Desmarcar todos</button>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="text-muted small" id="resumo-selecao">0 selecionado(s)</span>
                <button type="button" class="btn btn-success" id="btn-confirmar" disabled>
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-confirmar" role="status"></span>
                    Confirmar vínculos
                </button>
            </div>
        </div>
    </div>

    <div class="card mb-3" id="card-novas">
        <div class="card-header">
            <h5 class="header-title mb-0">
                Novas lojas <span class="badge bg-success-subtle text-success ms-1" id="count-novas">0</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-centered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="check-all-novas"></th>
                            <th>Linha</th>
                            <th>ID CIGAM</th>
                            <th>Cliente</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-novas"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3 d-none" id="card-sem-alteracoes">
        <div class="card-header">
            <h5 class="header-title mb-0">
                Já na carteira <span class="badge bg-secondary-subtle text-secondary ms-1" id="count-sem-alteracoes">0</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-centered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Linha</th>
                            <th>ID CIGAM</th>
                            <th>Cliente</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-sem-alteracoes"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3 d-none" id="card-erros">
        <div class="card-header">
            <h5 class="header-title mb-0">
                Erros <span class="badge bg-danger-subtle text-danger ms-1" id="count-erros">0</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-centered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Linha</th>
                            <th>ID CIGAM</th>
                            <th>Erros</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-erros"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="resumo-final" class="card d-none">
    <div class="card-header">
        <h5 class="header-title mb-0">Resumo da importação</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center mb-3">
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="fs-22 fw-bold text-success" id="resumo-criadas">0</div>
                    <div class="text-muted small">Lojas vinculadas</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="fs-22 fw-bold text-secondary" id="resumo-ignoradas">0</div>
                    <div class="text-muted small">Ignoradas</div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" id="btn-nova-importacao">Nova importação</button>
            <a href="#" class="btn btn-primary" id="btn-voltar-lista">Voltar para editar carteira</a>
        </div>
    </div>
</div>
