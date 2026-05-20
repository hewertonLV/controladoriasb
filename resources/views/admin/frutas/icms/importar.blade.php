@extends('layouts.app')

@section('title', 'Importar ICMS de Frutas')
@section('page-title', 'Importar ICMS de Frutas')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Importação de ICMS por planilha Excel</h4>
                <p class="text-muted mb-0">
                    Layout fixo (linha 1 = cabeçalho). A fruta deve já existir no cadastro.
                    <code>A</code> ID CIGAM ou nome · <code>B</code> Estado (ID, sigla ou nome) ·
                    <code>C/D</code> ICMS compra nacional + UM · <code>E/F</code> compra exterior + UM ·
                    <code>G/H</code> venda fora do estado + UM · <code>I/J</code> venda dentro do estado + UM.
                    Compra: KG ou UM. Venda em PE: use <code>PCT</code> para percentual.
                </p>
            </div>
            <a href="{{ route('admin.frutas.icms.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar para ICMS
            </a>
        </div>
        <div class="card-body">
            <form id="form-iniciar" class="row g-2 align-items-end" novalidate>
                <div class="col-md-8">
                    <label for="arquivo" class="form-label">Planilha (.xlsx ou .xls, até 5 MB)</label>
                    <input type="file" name="arquivo" id="arquivo"
                           class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div class="col-md-4 d-grid">
                    <button type="submit" class="btn btn-primary" id="btn-iniciar">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-iniciar" role="status"></span>
                        <i class="ri-play-circle-line me-1"></i> Iniciar análise
                    </button>
                </div>
            </form>

            <div id="alerta-preview" class="alert d-none mt-3" role="alert"></div>
        </div>
    </div>

    {{-- Card de progresso (visível durante PROCESSANDO/AGUARDANDO) --}}
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
                     id="progresso-barra"
                     role="progressbar"
                     style="width: 0%">0%</div>
            </div>
            <div class="text-muted small mt-2" id="progresso-texto">Aguardando processamento...</div>

            <div class="row g-2 mt-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="fs-20 fw-bold text-success" id="progresso-novas">0</div>
                        <div class="text-muted small">Novas</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="fs-20 fw-bold text-warning" id="progresso-atualizacoes">0</div>
                        <div class="text-muted small">Alterações</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="fs-20 fw-bold text-secondary" id="progresso-sem-alteracoes">0</div>
                        <div class="text-muted small">Sem alterações</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
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
                <button type="button" class="btn btn-sm btn-soft-primary" id="btn-selecionar-tudo">
                    <i class="ri-checkbox-multiple-line me-1"></i> Selecionar todos
                </button>
                <button type="button" class="btn btn-sm btn-soft-secondary" id="btn-desmarcar-tudo">
                    <i class="ri-checkbox-multiple-blank-line me-1"></i> Desmarcar todos
                </button>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="text-muted small" id="resumo-selecao">0 selecionado(s)</span>
                    <button type="button" class="btn btn-success" id="btn-confirmar" disabled>
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-confirmar" role="status"></span>
                        <i class="ri-check-double-line me-1"></i> Confirmar importação
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="card-novas">
            <div class="card-header">
                <h5 class="header-title mb-0">
                    <i class="ri-add-circle-line text-success me-1"></i>
                    Novos ICMS <span class="badge bg-success-subtle text-success ms-1" id="count-novas">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th style="width: 36px;">
                                    <input type="checkbox" class="form-check-input" id="check-all-novas">
                                </th>
                                <th>Linha</th>
                                <th>Fruta</th>
                                <th>Estado</th>
                                <th>Compra nac.</th>
                                <th>Compra ext.</th>
                                <th>Venda imp.</th>
                                <th>Venda nac.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-novas"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="card-atualizacoes">
            <div class="card-header">
                <h5 class="header-title mb-0">
                    <i class="ri-edit-circle-line text-warning me-1"></i>
                    Frutas com alterações <span class="badge bg-warning-subtle text-warning ms-1" id="count-atualizacoes">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th style="width: 36px;">
                                    <input type="checkbox" class="form-check-input" id="check-all-atualizacoes">
                                </th>
                                <th>Linha</th>
                                <th>ID CIGAM</th>
                                <th>Fruta</th>
                                <th>Campos alterados</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-atualizacoes"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3 d-none" id="card-sem-alteracoes">
            <div class="card-header">
                <h5 class="header-title mb-0">
                    <i class="ri-checkbox-circle-line text-muted me-1"></i>
                    Sem alterações <span class="badge bg-secondary-subtle text-secondary ms-1" id="count-sem-alteracoes">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th>Linha</th>
                                <th>ID CIGAM</th>
                                <th>Fruta</th>
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
                    <i class="ri-error-warning-line text-danger me-1"></i>
                    Erros <span class="badge bg-danger-subtle text-danger ms-1" id="count-erros">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
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
            <h5 class="header-title mb-0"><i class="ri-checkbox-circle-fill text-success me-1"></i> Resumo da importação</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center mb-3">
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-success" id="resumo-criadas">0</div>
                        <div class="text-muted small">Criadas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-warning" id="resumo-atualizadas">0</div>
                        <div class="text-muted small">Atualizadas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-secondary" id="resumo-ignoradas">0</div>
                        <div class="text-muted small">Ignoradas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-danger" id="resumo-erros">0</div>
                        <div class="text-muted small">Erros</div>
                    </div>
                </div>
            </div>

            <div id="resumo-erros-lista" class="alert alert-danger d-none"></div>

            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-light" id="btn-nova-importacao">
                    <i class="ri-refresh-line me-1"></i> Nova importação
                </button>
                <a href="{{ route('admin.frutas.index') }}" class="btn btn-primary">
                    <i class="ri-list-check-2 me-1"></i> Ir para Frutas
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const urlIniciar = @json(route('admin.frutas.icms.importar.iniciar'));

        const formIniciar = document.getElementById('form-iniciar');
        const inputArquivo = document.getElementById('arquivo');
        const btnIniciar = document.getElementById('btn-iniciar');
        const spinIniciar = document.getElementById('spinner-iniciar');
        const alertaPreview = document.getElementById('alerta-preview');

        const cardProgresso = document.getElementById('card-progresso');
        const progressoStatusTitulo = document.getElementById('progresso-status-titulo');
        const progressoArquivo = document.getElementById('progresso-arquivo');
        const progressoPercentual = document.getElementById('progresso-percentual');
        const progressoBarra = document.getElementById('progresso-barra');
        const progressoTexto = document.getElementById('progresso-texto');
        const progressoNovas = document.getElementById('progresso-novas');
        const progressoAtual = document.getElementById('progresso-atualizacoes');
        const progressoSem = document.getElementById('progresso-sem-alteracoes');
        const progressoErros = document.getElementById('progresso-erros');

        const resultado = document.getElementById('resultado');
        const tbodyNovas = document.getElementById('tbody-novas');
        const tbodyAtual = document.getElementById('tbody-atualizacoes');
        const tbodySem = document.getElementById('tbody-sem-alteracoes');
        const tbodyErros = document.getElementById('tbody-erros');
        const cardSem = document.getElementById('card-sem-alteracoes');
        const cardErros = document.getElementById('card-erros');

        const countNovas = document.getElementById('count-novas');
        const countAtual = document.getElementById('count-atualizacoes');
        const countSem = document.getElementById('count-sem-alteracoes');
        const countErros = document.getElementById('count-erros');

        const checkAllNovas = document.getElementById('check-all-novas');
        const checkAllAtual = document.getElementById('check-all-atualizacoes');
        const btnSelTudo = document.getElementById('btn-selecionar-tudo');
        const btnSelNada = document.getElementById('btn-desmarcar-tudo');
        const btnConfirmar = document.getElementById('btn-confirmar');
        const spinConfirmar = document.getElementById('spinner-confirmar');
        const resumoSelecao = document.getElementById('resumo-selecao');
        const btnNovaImportacao = document.getElementById('btn-nova-importacao');
        const resumoFinal = document.getElementById('resumo-final');

        let importacaoAtiva = null; // { uuid, urls: { status, resultado, confirmar } }
        let preview = { novas: [], atualizacoes: [], sem_alteracoes: [], erros: [] };
        let pollTimer = null;

        const POLL_INTERVAL_MS = 1500;

        function escapeHtml(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, c => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            })[c]);
        }
        function fmtKg(v) {
            const n = parseFloat(String(v || '0').replace(',', '.'));
            if (Number.isNaN(n)) return '0,00';
            return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function fmtCampoValor(campo, v) {
            if (campo === 'kg_por_unidade_medicao') return fmtKg(v);
            if (campo === 'icms_ex_compra' || campo === 'icms_na_compra' || campo === 'icms_venda') return fmtKg(v);
            if (v === null || v === undefined || v === '') return '—';
            return escapeHtml(v);
        }
        function showAlerta(tipo, mensagem) {
            alertaPreview.className = 'alert mt-3 alert-' + tipo;
            alertaPreview.classList.remove('d-none');
            alertaPreview.innerHTML = mensagem;
        }
        function hideAlerta() {
            alertaPreview.classList.add('d-none');
            alertaPreview.innerHTML = '';
        }
        function setLoading(button, spinner, loading) {
            if (button) button.disabled = loading;
            if (spinner) spinner.classList.toggle('d-none', !loading);
        }
        function resetProgressoUI() {
            cardProgresso.classList.add('d-none');
            progressoBarra.style.width = '0%';
            progressoBarra.textContent = '0%';
            progressoBarra.classList.remove('bg-success', 'bg-danger');
            progressoBarra.classList.add('progress-bar-striped', 'progress-bar-animated');
            progressoPercentual.textContent = '0%';
            progressoTexto.textContent = 'Aguardando processamento...';
            progressoStatusTitulo.textContent = 'Analisando planilha...';
            progressoArquivo.textContent = '\u00a0';
            progressoNovas.textContent = '0';
            progressoAtual.textContent = '0';
            progressoSem.textContent = '0';
            progressoErros.textContent = '0';
        }
        function aplicarStatusNaUI(s) {
            if (s.arquivo_original) progressoArquivo.textContent = s.arquivo_original;
            const pct = Math.max(0, Math.min(100, parseInt(s.percentual || 0, 10)));
            progressoBarra.style.width = pct + '%';
            progressoBarra.textContent = pct + '%';
            progressoPercentual.textContent = pct + '%';

            progressoNovas.textContent = s.novas_count || 0;
            progressoAtual.textContent = s.atualizacoes_count || 0;
            progressoSem.textContent = s.sem_alteracoes_count || 0;
            progressoErros.textContent = s.erros_count || 0;

            if (s.status === 'AGUARDANDO') {
                progressoStatusTitulo.textContent = 'Na fila — aguardando worker...';
                progressoTexto.textContent = 'A planilha foi enviada e está aguardando o processamento na fila.';
            } else if (s.status === 'PROCESSANDO') {
                progressoStatusTitulo.textContent = 'Analisando planilha...';
                if (s.total_linhas > 0) {
                    progressoTexto.textContent = 'Processados ' + (s.linhas_processadas || 0) + ' de ' + s.total_linhas + ' registros.';
                } else {
                    progressoTexto.textContent = 'Processando...';
                }
            } else if (s.status === 'CONCLUIDO') {
                progressoStatusTitulo.textContent = 'Análise concluída.';
                progressoTexto.textContent = 'Processados ' + (s.linhas_processadas || 0) + ' de ' + (s.total_linhas || 0) + ' registros.';
                progressoBarra.classList.remove('progress-bar-animated');
                progressoBarra.classList.add('bg-success');
            } else if (s.status === 'FALHOU') {
                progressoStatusTitulo.textContent = 'Falhou.';
                progressoTexto.textContent = s.erro_mensagem || 'O processamento falhou.';
                progressoBarra.classList.remove('progress-bar-animated');
                progressoBarra.classList.add('bg-danger');
            }
        }

        function renderIcmsResumo(d) {
            if (!d) return '—';
            const entNac = d.entrada_nacional_kg ?? d.compra_nacional ?? '0';
            const entInt = d.entrada_internacional_kg ?? d.compra_exterior ?? '0';
            const vdNac = d.saida_nacional_dentro_pct ?? d.venda_nacional ?? '0';
            const vfNac = d.saida_nacional_fora_pct ?? d.venda_importada ?? '0';
            const vdInt = d.saida_internacional_dentro_pct ?? vdNac;
            const vfInt = d.saida_internacional_fora_pct ?? vfNac;
            return [
                'Ent.N ' + fmtKg(entNac) + ' kg',
                'Ent.I ' + fmtKg(entInt) + ' kg',
                'V.Nac ' + fmtKg(vdNac) + '/' + fmtKg(vfNac) + '%',
                'V.Intl ' + fmtKg(vdInt) + '/' + fmtKg(vfInt) + '%',
            ].join(' · ');
        }
        function renderNovas(lista) {
            tbodyNovas.innerHTML = '';
            countNovas.textContent = lista.length;
            lista.forEach(item => {
                const d = item.dados_novos || item.dados || {};
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-nova" data-row="${item.row_id}" checked></td>
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.fruta_id_cigam || '')}</code> ${escapeHtml(item.fruta_nome || '')}</td>
                    <td>${escapeHtml(item.estado_ref || '')}</td>
                    <td colspan="4" class="small">${renderIcmsResumo(d)}</td>
                `;
                tbodyNovas.appendChild(tr);
            });
            checkAllNovas.checked = lista.length > 0;
        }

        function renderAtualizacoes(lista) {
            tbodyAtual.innerHTML = '';
            countAtual.textContent = lista.length;
            lista.forEach(item => {
                const diffs = (item.campos_alterados || []).map(c => `
                    <div class="small mb-1">
                        <span class="text-muted">${escapeHtml(c.campo)}:</span>
                        <span class="text-decoration-line-through text-danger">${fmtCampoValor(c.campo, c.atual)}</span>
                        <i class="ri-arrow-right-line text-muted mx-1"></i>
                        <span class="text-success fw-semibold">${fmtCampoValor(c.campo, c.novo)}</span>
                    </div>
                `).join('');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-atual" data-row="${item.row_id}" checked></td>
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.fruta_id_cigam || '')}</code> ${escapeHtml(item.fruta_nome || '')}</td>
                    <td>${escapeHtml(item.estado_ref || '')}</td>
                    <td>${diffs}</td>
                `;
                tbodyAtual.appendChild(tr);
            });
            checkAllAtual.checked = lista.length > 0;
        }

        function renderSemAlteracoes(lista) {
            tbodySem.innerHTML = '';
            countSem.textContent = lista.length;
            cardSem.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.id_cigam)}</code></td>
                    <td>${escapeHtml(item.nome)}</td>
                `;
                tbodySem.appendChild(tr);
            });
        }

        function renderErros(lista) {
            tbodyErros.innerHTML = '';
            countErros.textContent = lista.length;
            cardErros.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const erros = (item.erros || []).map(e => `<li>${escapeHtml(e)}</li>`).join('');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.id_cigam || '—')}</code></td>
                    <td><ul class="mb-0 ps-3 text-danger small">${erros}</ul></td>
                `;
                tbodyErros.appendChild(tr);
            });
        }

        function atualizarResumoSelecao() {
            const nNovas = document.querySelectorAll('.chk-nova:checked').length;
            const nAtual = document.querySelectorAll('.chk-atual:checked').length;
            const total = nNovas + nAtual;
            resumoSelecao.textContent = total + ' selecionado(s)';
            btnConfirmar.disabled = total === 0;
        }

        async function carregarResultadoEConfirmar() {
            try {
                const resp = await fetch(importacaoAtiva.urls.resultado, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await resp.json();
                if (!resp.ok) {
                    showAlerta('danger', escapeHtml(data.message || 'Erro ao carregar o resultado.'));
                    return;
                }
                preview = {
                    novas: data.novas || [],
                    atualizacoes: data.atualizacoes || [],
                    sem_alteracoes: data.sem_alteracoes || [],
                    erros: data.erros || [],
                };
                renderNovas(preview.novas);
                renderAtualizacoes(preview.atualizacoes);
                renderSemAlteracoes(preview.sem_alteracoes);
                renderErros(preview.erros);
                resultado.classList.remove('d-none');
                atualizarResumoSelecao();
                if (preview.novas.length === 0 && preview.atualizacoes.length === 0) {
                    showAlerta('info', 'A planilha não trouxe novas frutas nem alterações para frutas existentes.');
                }
            } catch (err) {
                showAlerta('danger', 'Falha de comunicação ao buscar resultado: ' + escapeHtml(err.message));
            }
        }

        async function poll() {
            if (!importacaoAtiva) return;
            try {
                const resp = await fetch(importacaoAtiva.urls.status, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!resp.ok) {
                    if (resp.status === 404) {
                        showAlerta('danger', 'Importação não encontrada.');
                        pararPolling();
                        return;
                    }
                    schedulePoll();
                    return;
                }
                const s = await resp.json();
                aplicarStatusNaUI(s);
                if (s.status === 'CONCLUIDO') {
                    pararPolling();
                    await carregarResultadoEConfirmar();
                } else if (s.status === 'FALHOU') {
                    pararPolling();
                    showAlerta('danger', escapeHtml(s.erro_mensagem || 'Falha no processamento da planilha.'));
                } else {
                    schedulePoll();
                }
            } catch (err) {
                schedulePoll();
            }
        }

        function schedulePoll() {
            pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
        }
        function pararPolling() {
            if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
        }

        formIniciar.addEventListener('submit', async function (e) {
            e.preventDefault();
            hideAlerta();
            resultado.classList.add('d-none');
            resumoFinal.classList.add('d-none');
            pararPolling();
            resetProgressoUI();

            if (!inputArquivo.files.length) {
                showAlerta('warning', 'Selecione um arquivo.');
                return;
            }

            const formData = new FormData();
            formData.append('arquivo', inputArquivo.files[0]);
            formData.append('_token', csrfToken);

            setLoading(btnIniciar, spinIniciar, true);
            try {
                const resp = await fetch(urlIniciar, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await resp.json();
                if (!resp.ok) {
                    let msg = data.message || 'Erro ao iniciar análise.';
                    if (data.errors && data.errors.arquivo) {
                        msg = data.errors.arquivo.join(' ');
                    }
                    showAlerta('danger', escapeHtml(msg));
                    return;
                }
                importacaoAtiva = { uuid: data.uuid, urls: data.urls };
                cardProgresso.classList.remove('d-none');
                progressoArquivo.textContent = inputArquivo.files[0].name;
                schedulePoll();
            } catch (err) {
                showAlerta('danger', 'Falha de comunicação: ' + escapeHtml(err.message));
            } finally {
                setLoading(btnIniciar, spinIniciar, false);
            }
        });

        checkAllNovas.addEventListener('change', e => {
            document.querySelectorAll('.chk-nova').forEach(c => c.checked = e.target.checked);
            atualizarResumoSelecao();
        });
        checkAllAtual.addEventListener('change', e => {
            document.querySelectorAll('.chk-atual').forEach(c => c.checked = e.target.checked);
            atualizarResumoSelecao();
        });
        btnSelTudo.addEventListener('click', () => {
            document.querySelectorAll('.chk-nova, .chk-atual').forEach(c => c.checked = true);
            checkAllNovas.checked = true;
            checkAllAtual.checked = true;
            atualizarResumoSelecao();
        });
        btnSelNada.addEventListener('click', () => {
            document.querySelectorAll('.chk-nova, .chk-atual').forEach(c => c.checked = false);
            checkAllNovas.checked = false;
            checkAllAtual.checked = false;
            atualizarResumoSelecao();
        });
        document.addEventListener('change', e => {
            if (e.target.matches('.chk-nova, .chk-atual')) atualizarResumoSelecao();
        });

        btnConfirmar.addEventListener('click', async () => {
            hideAlerta();
            if (!importacaoAtiva) {
                showAlerta('warning', 'Inicie uma análise antes de confirmar.');
                return;
            }
            const rowIdsNovas = Array.from(document.querySelectorAll('.chk-nova:checked')).map(c => Number(c.dataset.row));
            const rowIdsAtual = Array.from(document.querySelectorAll('.chk-atual:checked')).map(c => Number(c.dataset.row));

            if (rowIdsNovas.length === 0 && rowIdsAtual.length === 0) {
                showAlerta('warning', 'Selecione ao menos um fruta.');
                return;
            }

            setLoading(btnConfirmar, spinConfirmar, true);
            try {
                const resp = await fetch(importacaoAtiva.urls.confirmar, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        row_ids_novas: rowIdsNovas,
                        row_ids_atualizacoes: rowIdsAtual,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    showAlerta('danger', escapeHtml(data.message || 'Erro ao gravar.'));
                    return;
                }
                const r = data.resumo || {};
                document.getElementById('resumo-criadas').textContent = r.aplicadas || r.criadas || 0;
                document.getElementById('resumo-atualizadas').textContent = r.atualizadas || 0;
                document.getElementById('resumo-ignoradas').textContent = r.ignoradas || 0;
                document.getElementById('resumo-erros').textContent = (r.erros || []).length;

                const listaErros = document.getElementById('resumo-erros-lista');
                if ((r.erros || []).length > 0) {
                    listaErros.innerHTML = '<ul class="mb-0">' +
                        r.erros.map(e => `<li><strong>${escapeHtml(e.linha)}</strong>: ${(e.erros||[]).map(escapeHtml).join('; ')}</li>`).join('') +
                        '</ul>';
                    listaErros.classList.remove('d-none');
                } else {
                    listaErros.classList.add('d-none');
                }

                resultado.classList.add('d-none');
                cardProgresso.classList.add('d-none');
                resumoFinal.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (err) {
                showAlerta('danger', 'Falha de comunicação: ' + escapeHtml(err.message));
            } finally {
                setLoading(btnConfirmar, spinConfirmar, false);
            }
        });

        btnNovaImportacao.addEventListener('click', () => {
            inputArquivo.value = '';
            preview = { novas: [], atualizacoes: [], sem_alteracoes: [], erros: [] };
            importacaoAtiva = null;
            resultado.classList.add('d-none');
            resumoFinal.classList.add('d-none');
            resetProgressoUI();
            hideAlerta();
            pararPolling();
        });

        window.addEventListener('beforeunload', pararPolling);
    })();
    </script>
    @endpush
@endsection
