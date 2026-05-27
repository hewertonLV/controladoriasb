@extends('layouts.app')

@section('title', 'Importar vínculos — Frutas por loja')
@section('page-title', 'Importar vínculos — Frutas por loja')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Importação por planilha Excel</h4>
                <p class="text-muted mb-0">
                    Layout (linha 1 = cabeçalho livre):
                    <code>A</code> Razão social ou nome da loja ·
                    <code>B</code> Nome da fruta.
                    Modelo: <code>planilhas/fruta_loja_vinculo.xlsx</code>
                </p>
                @if ($faturamentoNome)
                    <p class="mb-0 small"><strong>Faturamento:</strong> {{ $faturamentoNome }}</p>
                @endif
            </div>
            <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $faturamentoId]) }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        </div>
        <div class="card-body">
            @if ($faturamentos->isEmpty())
                <div class="alert alert-warning mb-0">
                    Nenhuma unidade de faturamento disponível. Cadastre uma unidade que emite NF antes de importar.
                </div>
            @else
                <form id="form-iniciar" class="row g-2 align-items-end" novalidate>
                    <input type="hidden" name="faturamento" id="faturamento" value="{{ $faturamentoId }}">
                    <div class="col-md-4">
                        <label for="faturamento_select" class="form-label">Unidade de faturamento</label>
                        <select id="faturamento_select"
                                class="form-select"
                                data-search-select
                                data-placeholder="Selecione ou pesquise o faturamento">
                            @foreach ($faturamentos as $un)
                                <option value="{{ $un->id }}" @selected((int) $faturamentoId === $un->id)>{{ $un->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="arquivo" class="form-label">Planilha (.xlsx ou .xls, até 5 MB)</label>
                        <input type="file" name="arquivo" id="arquivo"
                               class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary" id="btn-iniciar">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-iniciar" role="status"></span>
                            <i class="ri-play-circle-line me-1"></i> Iniciar análise
                        </button>
                    </div>
                </form>
            @endif

            <div id="alerta-preview" class="alert d-none mt-3" role="alert"></div>
            <p class="text-muted small mt-2 mb-0">
                Se a análise ficar em <strong>«Na fila — aguardando worker…»</strong>, inicie o worker de importação
                (ex.: <code>php artisan queue:work --queue=captacao-importacao --sleep=1 --tries=1 --timeout=900</code>
                ou <code>docker compose restart worker-importacao</code> após atualizar o projeto).
            </p>
        </div>
    </div>

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
                        <div class="text-muted small">Novos vínculos</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-2">
                        <div class="fs-20 fw-bold text-secondary" id="progresso-sem-alteracoes">0</div>
                        <div class="text-muted small">Já vinculados</div>
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
                        <i class="ri-check-double-line me-1"></i> Confirmar vínculos
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="card-novas">
            <div class="card-header">
                <h5 class="header-title mb-0">
                    <i class="ri-add-circle-line text-success me-1"></i>
                    Novos vínculos <span class="badge bg-success-subtle text-success ms-1" id="count-novas">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="check-all-novas"></th>
                                <th>Linha</th>
                                <th>Loja (planilha)</th>
                                <th>Fruta</th>
                                <th>Loja encontrada</th>
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
                    <i class="ri-checkbox-circle-line text-muted me-1"></i>
                    Já vinculados <span class="badge bg-secondary-subtle text-secondary ms-1" id="count-sem-alteracoes">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th>Linha</th>
                                <th>Loja</th>
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
                                <th>Loja</th>
                                <th>Fruta</th>
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
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-success" id="resumo-criadas">0</div>
                        <div class="text-muted small">Vínculos criados</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-secondary" id="resumo-ignoradas">0</div>
                        <div class="text-muted small">Ignoradas</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="fs-22 fw-bold text-danger" id="resumo-erros">0</div>
                        <div class="text-muted small">Erros na gravação</div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-light" id="btn-nova-importacao">
                    <i class="ri-refresh-line me-1"></i> Nova importação
                </button>
                <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $faturamentoId]) }}" class="btn btn-primary" id="btn-voltar-lista">
                    <i class="ri-list-check-2 me-1"></i> Ir para Frutas por loja
                </a>
            </div>
        </div>
    </div>

    @include('admin.captacao._search-select-scripts')

    @push('scripts')
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const urlIniciar = @json(route('admin.captacao.frutas-por-loja.importar.iniciar'));

        const formIniciar = document.getElementById('form-iniciar');
        if (!formIniciar) return;

        const inputFaturamento = document.getElementById('faturamento');
        const selectFaturamento = document.getElementById('faturamento_select');
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
        const progressoSem = document.getElementById('progresso-sem-alteracoes');
        const progressoErros = document.getElementById('progresso-erros');

        const resultado = document.getElementById('resultado');
        const tbodyNovas = document.getElementById('tbody-novas');
        const tbodySem = document.getElementById('tbody-sem-alteracoes');
        const tbodyErros = document.getElementById('tbody-erros');
        const cardSem = document.getElementById('card-sem-alteracoes');
        const cardErros = document.getElementById('card-erros');

        const countNovas = document.getElementById('count-novas');
        const countSem = document.getElementById('count-sem-alteracoes');
        const countErros = document.getElementById('count-erros');

        const checkAllNovas = document.getElementById('check-all-novas');
        const btnSelTudo = document.getElementById('btn-selecionar-tudo');
        const btnSelNada = document.getElementById('btn-desmarcar-tudo');
        const btnConfirmar = document.getElementById('btn-confirmar');
        const spinConfirmar = document.getElementById('spinner-confirmar');
        const resumoSelecao = document.getElementById('resumo-selecao');
        const btnNovaImportacao = document.getElementById('btn-nova-importacao');
        const resumoFinal = document.getElementById('resumo-final');
        const btnVoltarLista = document.getElementById('btn-voltar-lista');

        let importacaoAtiva = null;
        let preview = { novas: [], sem_alteracoes: [], erros: [] };
        let pollTimer = null;
        const POLL_INTERVAL_MS = 1500;

        const listagemBaseUrl = @json(route('admin.captacao.frutas-por-loja.index'));

        function urlListagem(faturamentoId) {
            const sep = listagemBaseUrl.includes('?') ? '&' : '?';
            return listagemBaseUrl + sep + 'faturamento=' + encodeURIComponent(faturamentoId);
        }

        selectFaturamento?.addEventListener('change', () => {
            inputFaturamento.value = selectFaturamento.value;
            const url = new URL(window.location.href);
            url.searchParams.set('faturamento', selectFaturamento.value);
            window.history.replaceState({}, '', url);
            if (btnVoltarLista) {
                btnVoltarLista.href = urlListagem(selectFaturamento.value);
            }
        });

        function escapeHtml(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, c => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            })[c]);
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
            progressoSem.textContent = s.sem_alteracoes_count || 0;
            progressoErros.textContent = s.erros_count || 0;

            if (s.status === 'AGUARDANDO') {
                progressoStatusTitulo.textContent = 'Na fila — aguardando worker...';
                progressoTexto.textContent = 'A planilha foi enviada e aguarda processamento.';
            } else if (s.status === 'PROCESSANDO') {
                progressoStatusTitulo.textContent = 'Analisando planilha...';
                progressoTexto.textContent = s.total_linhas > 0
                    ? 'Processados ' + (s.linhas_processadas || 0) + ' de ' + s.total_linhas + ' linhas.'
                    : 'Processando...';
            } else if (s.status === 'CONCLUIDO') {
                progressoStatusTitulo.textContent = 'Análise concluída.';
                progressoBarra.classList.remove('progress-bar-animated');
                progressoBarra.classList.add('bg-success');
            } else if (s.status === 'FALHOU') {
                progressoStatusTitulo.textContent = 'Falhou.';
                progressoTexto.textContent = s.erro_mensagem || 'Falha no processamento.';
                progressoBarra.classList.remove('progress-bar-animated');
                progressoBarra.classList.add('bg-danger');
            }
        }

        function renderNovas(lista) {
            tbodyNovas.innerHTML = '';
            countNovas.textContent = lista.length;
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-nova" data-row="${item.row_id}" checked></td>
                    <td>${item.linha}</td>
                    <td>${escapeHtml(item.dados?.loja || item.loja || '')}</td>
                    <td>${escapeHtml(item.dados?.fruta || item.fruta || item.fruta_nome || '')}</td>
                    <td class="text-muted small">${escapeHtml(item.cliente_nome || '')}</td>
                `;
                tbodyNovas.appendChild(tr);
            });
            checkAllNovas.checked = lista.length > 0;
        }

        function renderSem(lista) {
            tbodySem.innerHTML = '';
            countSem.textContent = lista.length;
            cardSem.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha}</td>
                    <td>${escapeHtml(item.loja || '')}</td>
                    <td>${escapeHtml(item.fruta || item.fruta_nome || '')}</td>
                `;
                tbodySem.appendChild(tr);
            });
        }

        function renderErros(lista) {
            tbodyErros.innerHTML = '';
            countErros.textContent = lista.length;
            cardErros.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha}</td>
                    <td>${escapeHtml(item.loja || '')}</td>
                    <td>${escapeHtml(item.fruta || '')}</td>
                    <td class="text-danger small">${(item.erros || []).map(escapeHtml).join('; ')}</td>
                `;
                tbodyErros.appendChild(tr);
            });
        }

        function atualizarResumoSelecao() {
            const total = document.querySelectorAll('.chk-nova:checked').length;
            resumoSelecao.textContent = total + ' selecionado(s)';
            btnConfirmar.disabled = total === 0;
        }

        async function carregarResultado() {
            const resp = await fetch(importacaoAtiva.urls.resultado, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await resp.json();
            if (!resp.ok) {
                showAlerta('danger', escapeHtml(data.message || 'Erro ao carregar resultado.'));
                return;
            }
            preview = {
                novas: data.novas || [],
                sem_alteracoes: data.sem_alteracoes || [],
                erros: data.erros || [],
            };
            renderNovas(preview.novas);
            renderSem(preview.sem_alteracoes);
            renderErros(preview.erros);
            resultado.classList.remove('d-none');
            atualizarResumoSelecao();
            if (preview.novas.length === 0) {
                showAlerta('info', 'Nenhum vínculo novo para importar. Revise a aba «Já vinculados» ou «Erros».');
            }
        }

        async function poll() {
            if (!importacaoAtiva) return;
            try {
                const resp = await fetch(importacaoAtiva.urls.status, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!resp.ok) { schedulePoll(); return; }
                const s = await resp.json();
                aplicarStatusNaUI(s);
                if (s.status === 'CONCLUIDO') {
                    pararPolling();
                    await carregarResultado();
                } else if (s.status === 'FALHOU') {
                    pararPolling();
                    showAlerta('danger', escapeHtml(s.erro_mensagem || 'Falha no processamento.'));
                } else {
                    schedulePoll();
                }
            } catch (e) {
                schedulePoll();
            }
        }

        function schedulePoll() { pollTimer = setTimeout(poll, POLL_INTERVAL_MS); }
        function pararPolling() { if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; } }

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
            formData.append('faturamento', inputFaturamento.value);
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
                    if (data.errors) {
                        const parts = Object.values(data.errors).flat();
                        if (parts.length) msg = parts.join(' ');
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
        btnSelTudo.addEventListener('click', () => {
            document.querySelectorAll('.chk-nova').forEach(c => c.checked = true);
            checkAllNovas.checked = true;
            atualizarResumoSelecao();
        });
        btnSelNada.addEventListener('click', () => {
            document.querySelectorAll('.chk-nova').forEach(c => c.checked = false);
            checkAllNovas.checked = false;
            atualizarResumoSelecao();
        });
        document.addEventListener('change', e => {
            if (e.target.matches('.chk-nova')) atualizarResumoSelecao();
        });

        btnConfirmar.addEventListener('click', async () => {
            hideAlerta();
            if (!importacaoAtiva) return;
            const rowIdsNovas = Array.from(document.querySelectorAll('.chk-nova:checked')).map(c => Number(c.dataset.row));
            if (rowIdsNovas.length === 0) {
                showAlerta('warning', 'Selecione ao menos um vínculo.');
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
                    body: JSON.stringify({ row_ids_novas: rowIdsNovas }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    showAlerta('danger', escapeHtml(data.message || 'Erro ao gravar.'));
                    return;
                }
                const r = data.resumo || {};
                document.getElementById('resumo-criadas').textContent = r.vinculos_criados || 0;
                document.getElementById('resumo-ignoradas').textContent = r.ignoradas || 0;
                document.getElementById('resumo-erros').textContent = (r.erros || []).length;
                if (data.redirect && btnVoltarLista) {
                    btnVoltarLista.href = data.redirect;
                }
                resultado.classList.add('d-none');
                cardProgresso.classList.add('d-none');
                resumoFinal.classList.remove('d-none');
            } catch (err) {
                showAlerta('danger', 'Falha de comunicação: ' + escapeHtml(err.message));
            } finally {
                setLoading(btnConfirmar, spinConfirmar, false);
            }
        });

        btnNovaImportacao.addEventListener('click', () => {
            inputArquivo.value = '';
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
