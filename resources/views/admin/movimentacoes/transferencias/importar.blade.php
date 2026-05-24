@extends('layouts.app')

@section('title', 'Importar Transferências')
@section('page-title', 'Importar Transferências')

@section('content')
    <x-admin.import-legado-banner />
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Importação por planilha Excel</h4>
                <p class="text-muted mb-0">
                    Uma fruta por linha. Layout fixo — linha 1 é cabeçalho:
                    <code>A</code> CNPJ origem · <code>B</code> CNPJ destino ·
                    <code>C</code> ID CIGAM fruta · <code>D</code> Qtd (unidade de medição) ·
                    <code>E</code> Número da NF.
                </p>
                <p class="text-muted mb-0 small mt-1">
                    Modelo: <code>planilhas/transferencias_importacao.xlsx</code>
                </p>
            </div>
            <a href="{{ route('admin.movimentacoes.transferencias.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar para Transferências
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
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fs-20 fw-bold text-success" id="progresso-novas">0</div>
                        <div class="text-muted small">Prontas</div>
                    </div>
                </div>
                <div class="col-6">
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
                    <i class="ri-checkbox-multiple-line me-1"></i> Selecionar todas
                </button>
                <button type="button" class="btn btn-sm btn-soft-secondary" id="btn-desmarcar-tudo">
                    <i class="ri-checkbox-multiple-blank-line me-1"></i> Desmarcar todas
                </button>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="text-muted small" id="resumo-selecao">0 selecionada(s)</span>
                    <button type="button" class="btn btn-success" id="btn-confirmar" disabled>
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-confirmar" role="status"></span>
                        <i class="ri-check-double-line me-1"></i> Confirmar transferências
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="card-novas">
            <div class="card-header">
                <h5 class="header-title mb-0">
                    <i class="ri-truck-line text-success me-1"></i>
                    Linhas prontas <span class="badge bg-success-subtle text-success ms-1" id="count-novas">0</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">A origem vem da planilha. O destino é editável por NF — alterar uma NF aplica a todas as linhas da mesma origem com esse número.</p>
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
                                <th>Origem · Destino</th>
                                <th>Fruta · Qtd · NF</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-novas"></tbody>
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
                                <th>Referência</th>
                                <th>Motivo</th>
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
                        <div class="fs-22 fw-bold text-success" id="resumo-aplicadas">0</div>
                        <div class="text-muted small">Criadas</div>
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
                        <div class="text-muted small">Erros ao gravar</div>
                    </div>
                </div>
            </div>
            <div id="resumo-erros-lista" class="alert alert-danger d-none"></div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-light" id="btn-nova-importacao">
                    <i class="ri-refresh-line me-1"></i> Nova importação
                </button>
                <a href="{{ route('admin.movimentacoes.transferencias.index') }}" class="btn btn-primary">
                    <i class="ri-list-check-2 me-1"></i> Ir para Transferências
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const urlIniciar = @json(route('admin.movimentacoes.transferencias.importar.iniciar'));

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
        const progressoErros = document.getElementById('progresso-erros');
        const resultado = document.getElementById('resultado');
        const tbodyNovas = document.getElementById('tbody-novas');
        const tbodyErros = document.getElementById('tbody-erros');
        const cardErros = document.getElementById('card-erros');
        const countNovas = document.getElementById('count-novas');
        const countErros = document.getElementById('count-erros');
        const checkAllNovas = document.getElementById('check-all-novas');
        const btnSelTudo = document.getElementById('btn-selecionar-tudo');
        const btnSelNada = document.getElementById('btn-desmarcar-tudo');
        const btnConfirmar = document.getElementById('btn-confirmar');
        const spinConfirmar = document.getElementById('spinner-confirmar');
        const resumoSelecao = document.getElementById('resumo-selecao');
        const btnNovaImportacao = document.getElementById('btn-nova-importacao');
        const resumoFinal = document.getElementById('resumo-final');

        let importacaoAtiva = null;
        let preview = { novas: [], erros: [] };
        let empresasDestino = [];
        let pollTimer = null;
        const POLL_INTERVAL_MS = 1500;

        function escapeHtml(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, c => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            })[c]);
        }
        function fmtDoc(v) {
            const d = String(v || '').replace(/\D/g,'');
            if (d.length === 11) return d.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            if (d.length === 14) return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            return d;
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
            progressoErros.textContent = '0';
        }
        function aplicarStatusNaUI(s) {
            if (s.arquivo_original) progressoArquivo.textContent = s.arquivo_original;
            const pct = Math.max(0, Math.min(100, parseInt(s.percentual || 0, 10)));
            progressoBarra.style.width = pct + '%';
            progressoBarra.textContent = pct + '%';
            progressoPercentual.textContent = pct + '%';
            progressoNovas.textContent = s.novas_count || 0;
            progressoErros.textContent = s.erros_count || 0;
            if (s.status === 'AGUARDANDO') {
                progressoStatusTitulo.textContent = 'Na fila — aguardando worker...';
                progressoTexto.textContent = 'Aguardando fila transferencias-importacao.';
            } else if (s.status === 'PROCESSANDO') {
                progressoStatusTitulo.textContent = 'Analisando planilha...';
                progressoTexto.textContent = s.total_linhas > 0
                    ? 'Processados ' + (s.linhas_processadas || 0) + ' de ' + s.total_linhas + ' registros.'
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
        function resumoTransferencia(d) {
            const fruta = escapeHtml(d.fruta_nome || d.id_cigam_fruta || '—');
            const um = escapeHtml(d.qtd_fruta_um);
            const umed = escapeHtml(d.unidade_medicao || 'UM');
            const nf = escapeHtml(d.numero_nf_origem || '—');
            return `${fruta} · <strong>${um}</strong> ${umed} · NF ${nf}`;
        }
        function rotuloOrigem(d) {
            const o = fmtDoc(d.cnpj_origem);
            const no = escapeHtml(d.nome_origem || '');
            return `<span class="text-muted small">${o}</span> ${no}`;
        }
        function opcoesSelectDestino(idSelecionado) {
            const id = Number(idSelecionado);
            return empresasDestino.map(e => {
                const cnpj = fmtDoc(e.cnpj);
                const label = escapeHtml(e.label || '—');
                const sel = Number(e.id) === id ? ' selected' : '';
                return `<option value="${e.id}"${sel}>${label}${cnpj ? ' · ' + cnpj : ''}</option>`;
            }).join('');
        }
        function chaveGrupoDestino(d) {
            return String(d.id_empresa_origem || '') + '|' + String(d.numero_nf_origem || '');
        }
        function agruparNovasPorNf(lista) {
            const grupos = new Map();
            lista.forEach(item => {
                const d = item.dados || {};
                const chave = chaveGrupoDestino(d);
                if (!grupos.has(chave)) {
                    grupos.set(chave, []);
                }
                grupos.get(chave).push(item);
            });
            return Array.from(grupos.entries()).sort((a, b) => {
                const la = a[1][0]?.linha ?? 0;
                const lb = b[1][0]?.linha ?? 0;
                return la - lb;
            });
        }
        function selectDestinoGrupo(chave, rowIds, idEmpresaDestino, nf) {
            if (!empresasDestino.length) {
                return '<span class="text-muted small">—</span>';
            }
            const nfLabel = escapeHtml(nf || '—');
            const rowIdsAttr = rowIds.join(',');
            return `<label class="form-label text-muted small mb-0 mt-1">Destino · NF ${nfLabel}</label>
                <select class="form-select form-select-sm sel-destino-grupo"
                    data-grupo="${escapeHtml(chave)}"
                    data-row-ids="${rowIdsAttr}"
                    aria-label="Destino para NF ${nfLabel}">
                    ${opcoesSelectDestino(idEmpresaDestino)}
                </select>`;
        }
        function formatarErrosLinha(item) {
            const lista = item.erros || [];
            if (!lista.length) return '<span class="text-muted">—</span>';
            return '<ul class="mb-0 ps-3 text-danger small">' +
                lista.map(e => '<li>' + escapeHtml(e) + '</li>').join('') + '</ul>';
        }
        function renderNovas(lista) {
            tbodyNovas.innerHTML = '';
            countNovas.textContent = lista.length;
            agruparNovasPorNf(lista).forEach(([, itens]) => {
                itens.forEach((item, idx) => {
                    const d = item.dados || {};
                    const idDestino = item.id_empresa_destino ?? d.id_empresa_destino ?? '';
                    const tr = document.createElement('tr');
                    const destinoCell = idx === 0
                        ? `<td class="small align-top" rowspan="${itens.length}">
                            <div>${rotuloOrigem(d)}</div>
                            ${selectDestinoGrupo(chaveGrupoDestino(d), itens.map(i => i.row_id), idDestino, d.numero_nf_origem)}
                           </td>`
                        : '';
                    tr.innerHTML = `
                        <td><input type="checkbox" class="form-check-input chk-nova" data-row="${item.row_id}" checked></td>
                        <td>${item.linha}</td>
                        ${destinoCell}
                        <td class="small">${resumoTransferencia(d)}</td>
                    `;
                    tbodyNovas.appendChild(tr);
                });
            });
            checkAllNovas.checked = lista.length > 0;
        }
        function destinosPorRowSelecionados(rowIdsSelecionados) {
            const selecionados = new Set(rowIdsSelecionados);
            const out = {};
            document.querySelectorAll('.sel-destino-grupo').forEach(sel => {
                const ids = String(sel.dataset.rowIds || '').split(',').map(Number).filter(n => n > 0);
                const destinoId = Number(sel.value);
                ids.forEach(rowId => {
                    if (selecionados.has(rowId)) {
                        out[rowId] = destinoId;
                    }
                });
            });
            return out;
        }
        function renderErros(lista) {
            tbodyErros.innerHTML = '';
            countErros.textContent = lista.length;
            cardErros.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha ?? '—'}</td>
                    <td class="small"><code>${escapeHtml(item.chave || '—')}</code></td>
                    <td>${formatarErrosLinha(item)}</td>
                `;
                tbodyErros.appendChild(tr);
            });
        }
        function atualizarResumoSelecao() {
            const n = document.querySelectorAll('.chk-nova:checked').length;
            resumoSelecao.textContent = n + ' selecionada(s)';
            btnConfirmar.disabled = n === 0;
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
            preview = { novas: data.novas || [], erros: data.erros || [] };
            empresasDestino = data.empresas_destino || [];
            renderErros(preview.erros);
            renderNovas(preview.novas);
            resultado.classList.remove('d-none');
            atualizarResumoSelecao();
            if (preview.erros.length > 0) {
                showAlerta('warning', '<strong>' + preview.erros.length + '</strong> linha(s) com erro. Confira a tabela abaixo.');
            } else if (preview.novas.length === 0) {
                showAlerta('info', 'Nenhuma linha válida para transferência.');
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
            } catch (e) { schedulePoll(); }
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
                    let msg = data.message || 'Erro ao iniciar.';
                    if (data.errors?.arquivo) msg = data.errors.arquivo.join(' ');
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
            const rowIds = Array.from(document.querySelectorAll('.chk-nova:checked')).map(c => Number(c.dataset.row));
            if (!rowIds.length) {
                showAlerta('warning', 'Selecione ao menos uma linha.');
                return;
            }
            const idEmpresaDestinoPorRow = destinosPorRowSelecionados(rowIds);
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
                        row_ids_novas: rowIds,
                        id_empresa_destino_por_row: idEmpresaDestinoPorRow,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    showAlerta('danger', escapeHtml(data.message || 'Erro ao gravar.'));
                    return;
                }
                const r = data.resumo || {};
                document.getElementById('resumo-aplicadas').textContent = r.aplicadas || 0;
                document.getElementById('resumo-ignoradas').textContent = r.ignoradas || 0;
                document.getElementById('resumo-erros').textContent = (r.erros || []).length;
                const listaErros = document.getElementById('resumo-erros-lista');
                if ((r.erros || []).length) {
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
                showAlerta('danger', 'Falha: ' + escapeHtml(err.message));
            } finally {
                setLoading(btnConfirmar, spinConfirmar, false);
            }
        });

        btnNovaImportacao.addEventListener('click', () => {
            inputArquivo.value = '';
            importacaoAtiva = null;
            preview = { novas: [], erros: [] };
            empresasDestino = [];
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
