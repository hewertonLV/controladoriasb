@extends('layouts.app')

@section('title', 'Importar Estoques')
@section('page-title', 'Importar Estoques')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Importação por planilha Excel</h4>
                <p class="text-muted mb-0">
                    Posição inicial por loja (unidade) e fruta. Layout fixo — linha 1 é cabeçalho:
                    <code>A</code> ID CIGAM unidade · <code>B</code> ID CIGAM fruta ·
                    <code>C</code> Qtd (unidade de medição — pode ser <strong>negativa, zero ou positiva</strong>) · <code>D</code> Preço total (R$, também aceita negativo).
                    O sistema calcula kg, preço médio e valor acumulado com base no cadastro da fruta.
                    Uma planilha pode trazer várias unidades; após a análise, confirme as linhas desejadas.
                </p>
                <p class="text-muted mb-0 small mt-1">
                    Modelo: <code>planilhas/estoques_importacao.xlsx</code> (exemplo na linha 2).
                </p>
            </div>
            <a href="{{ route('admin.estoques.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar para Estoques
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
                    Novas posições <span class="badge bg-success-subtle text-success ms-1" id="count-novas">0</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Switch ligado (padrão): soma o CO vigente da unidade ao preço médio/kg da linha.</p>
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
                                <th>Unidade / Fruta</th>
                                <th>Qtd (UM) · Preço total</th>
                                <th style="min-width: 180px;">Incluir CO</th>
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
                    Posições com alteração <span class="badge bg-warning-subtle text-warning ms-1" id="count-atualizacoes">0</span>
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
                                <th>Unidade / Fruta</th>
                                <th>Valores atuais</th>
                                <th>Alterações</th>
                                <th style="min-width: 180px;">Incluir CO</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-atualizacoes"></tbody>
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
                                <th>Unidade · Fruta</th>
                                <th>Motivo(s)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-erros"></tbody>
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
                                <th>Unidade · Fruta</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-sem-alteracoes"></tbody>
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
                        <div class="text-muted small">Aplicadas</div>
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
                        <div class="text-muted small">Erros</div>
                    </div>
                </div>
            </div>

            <div id="resumo-erros-lista" class="alert alert-danger d-none"></div>

            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-light" id="btn-nova-importacao">
                    <i class="ri-refresh-line me-1"></i> Nova importação
                </button>
                <a href="{{ route('admin.estoques.index') }}" class="btn btn-primary">
                    <i class="ri-list-check-2 me-1"></i> Ir para Estoques
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const urlIniciar = @json(route('admin.estoques.importar.iniciar'));

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
        function fmtDoc(v) {
            const d = String(v || '').replace(/\D/g,'');
            if (d.length === 11) return d.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            if (d.length === 14) return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            return d;
        }
        function fmtCampoValor(campo, v) {
            if (campo === 'cnpj_cpf') return fmtDoc(v);
            if (campo === 'qtd_fruta_um' || campo === 'valor_total' || campo === 'qtd_fruta_kg' || campo === 'preco_medio_kg') {
                return escapeHtml(String(v));
            }
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
                progressoTexto.textContent = 'A planilha foi enviada e está aguardando o processamento na fila estoques-importacao.';
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

        function resumoPosicao(d, aplicarCo) {
            const um = escapeHtml(d.qtd_fruta_um);
            const qtdUm = parseFloat(d.qtd_fruta_um || 0);
            const kg = parseFloat(d.qtd_fruta_kg || 0);
            const semQuantidade = Math.abs(qtdUm) < 0.005 || Math.abs(kg) < 0.005;
            let precoKg = semQuantidade ? 0 : parseFloat(d.preco_medio_kg || 0);
            const co = parseFloat(d.custo_operacional_kg || 0);
            if (!semQuantidade && aplicarCo && co > 0) {
                precoKg = Math.round((precoKg + co) * 100) / 100;
            }
            const total = Math.round(kg * precoKg * 100) / 100;
            const kgFmt = escapeHtml(String(kg.toFixed(2)));
            const precoKgFmt = escapeHtml(precoKg.toFixed(2));
            const totalFmt = escapeHtml(total.toFixed(2));
            const coHint = aplicarCo && co > 0
                ? `<br><span class="text-muted">+ CO R$ ${escapeHtml(co.toFixed(2))}/kg</span>`
                : '';
            return `${um} UM · R$ ${totalFmt} total<br><span class="text-muted">→ ${kgFmt} kg · R$ ${precoKgFmt}/kg</span>${coHint}`;
        }

        function fmtMoeda(v) {
            const n = parseFloat(v);
            if (Number.isNaN(n)) return '0,00';
            return n.toFixed(2).replace('.', ',');
        }

        function switchCustoOperacional(rowId, d) {
            const co = d.custo_operacional_kg ?? '0.00';
            const coAttr = escapeHtml(String(co));
            return `<div class="form-check form-switch mb-0">
                <input type="checkbox" class="form-check-input sw-co-importacao" role="switch"
                    data-row="${rowId}" data-custo-kg="${coAttr}" checked
                    aria-label="Incluir custo operacional da unidade">
                <label class="form-check-label small sw-co-label">Sim · R$ ${fmtMoeda(co)}/kg</label>
            </div>`;
        }

        function atualizarLabelCoSwitch(sw) {
            const label = sw.closest('.form-check')?.querySelector('.sw-co-label');
            if (!label) return;
            const co = sw.dataset.custoKg || '0';
            const estado = sw.checked ? 'Sim' : 'Não';
            label.textContent = estado + ' · R$ ' + fmtMoeda(co) + '/kg';
        }

        function coPorRowSelecionados(rowIdsSelecionados) {
            const selecionados = new Set(rowIdsSelecionados);
            const out = {};
            document.querySelectorAll('.sw-co-importacao').forEach(sw => {
                const rowId = Number(sw.dataset.row);
                if (selecionados.has(rowId)) {
                    out[rowId] = sw.checked;
                }
            });
            return out;
        }

        function atualizarResumoPosicaoLinha(rowId) {
            const sw = document.querySelector('.sw-co-importacao[data-row="' + rowId + '"]');
            const cell = document.querySelector('[data-resumo-posicao-row="' + rowId + '"]');
            if (!sw || !cell) return;
            const d = cell._dadosPosicao;
            if (!d) return;
            const prefix = cell.dataset.resumoPrefix || '';
            cell.innerHTML = prefix + resumoPosicao(d, sw.checked);
        }

        function rotuloUnidadeFruta(item) {
            const d = item.dados || {};
            const un = d.id_cigam_unidade || d.id_cigam_unidade_original;
            const fr = d.id_cigam_fruta;
            if (un || fr) {
                return 'Un. <code>' + escapeHtml(un || '—') + '</code> · Fruta <code>' + escapeHtml(fr || '—') + '</code>';
            }
            const chave = item.chave || item.nome;
            if (chave && chave !== '|') {
                return '<code>' + escapeHtml(chave) + '</code>';
            }
            return '—';
        }

        function formatarErrosLinha(item) {
            const lista = item.erros || [];
            if (!Array.isArray(lista) || lista.length === 0) {
                return '<span class="text-muted">Erro não detalhado.</span>';
            }
            return '<ul class="mb-0 ps-3 text-danger small">' +
                lista.map(e => '<li>' + escapeHtml(e) + '</li>').join('') +
                '</ul>';
        }

        function renderNovas(lista) {
            if (!tbodyNovas) return;
            tbodyNovas.innerHTML = '';
            countNovas.textContent = lista.length;
            lista.forEach(item => {
                const d = item.dados;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-nova" data-row="${item.row_id}" checked></td>
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.chave)}</code></td>
                    <td class="small" data-resumo-posicao-row="${item.row_id}">${resumoPosicao(d, true)}</td>
                    <td class="align-middle">${switchCustoOperacional(item.row_id, d)}</td>
                `;
                const cell = tr.querySelector('[data-resumo-posicao-row]');
                if (cell) cell._dadosPosicao = d;
                tbodyNovas.appendChild(tr);
            });
            checkAllNovas.checked = lista.length > 0;
        }

        function renderAtualizacoes(lista) {
            if (!tbodyAtual) return;
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
                const dNovos = item.dados_novos || {};
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-atual" data-row="${item.row_id}" checked></td>
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.chave)}</code></td>
                    <td class="small">${resumoPosicao(item.dados_atuais, false)}</td>
                    <td>
                        ${diffs}
                        <div class="small mt-1 text-muted" data-resumo-posicao-row="${item.row_id}" data-resumo-prefix="Novo: ">Novo: ${resumoPosicao(dNovos, true)}</div>
                    </td>
                    <td class="align-middle">${switchCustoOperacional(item.row_id, dNovos)}</td>
                `;
                const cell = tr.querySelector('[data-resumo-posicao-row]');
                if (cell) cell._dadosPosicao = dNovos;
                tbodyAtual.appendChild(tr);
            });
            checkAllAtual.checked = lista.length > 0;
        }

        function renderSemAlteracoes(lista) {
            if (!tbodySem) return;
            tbodySem.innerHTML = '';
            countSem.textContent = lista.length;
            cardSem.classList.toggle('d-none', lista.length === 0);
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.linha}</td>
                    <td><code>${escapeHtml(item.chave)}</code></td>
                `;
                tbodySem.appendChild(tr);
            });
        }

        function renderErros(lista) {
            if (!tbodyErros) return;
            tbodyErros.innerHTML = '';
            countErros.textContent = lista.length;
            if (cardErros) {
                cardErros.classList.toggle('d-none', lista.length === 0);
            }
            lista.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-nowrap">${item.linha ?? '—'}</td>
                    <td class="small">${rotuloUnidadeFruta(item)}</td>
                    <td>${formatarErrosLinha(item)}</td>
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
                renderErros(preview.erros);
                renderNovas(preview.novas);
                renderAtualizacoes(preview.atualizacoes);
                renderSemAlteracoes(preview.sem_alteracoes);
                resultado.classList.remove('d-none');
                atualizarResumoSelecao();

                if (preview.erros.length > 0) {
                    const nErros = preview.erros.length;
                    showAlerta(
                        'warning',
                        '<strong>' + nErros + '</strong> linha(s) com erro na planilha. ' +
                        'Confira a tabela <em>Erros</em> abaixo — cada linha lista o motivo (unidade/fruta não encontrada, quantidade inválida, etc.).'
                    );
                    if (cardErros) {
                        cardErros.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else if (preview.novas.length === 0 && preview.atualizacoes.length === 0) {
                    showAlerta('info', 'A planilha não trouxe novas linhas nem alterações para estoques existentes.');
                }
            } catch (err) {
                console.error('[Importar estoques]', err);
                showAlerta('danger', 'Falha ao exibir o resultado da análise: ' + escapeHtml(err.message));
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
            if (e.target.matches('.sw-co-importacao')) {
                atualizarLabelCoSwitch(e.target);
                atualizarResumoPosicaoLinha(Number(e.target.dataset.row));
            }
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
                showAlerta('warning', 'Selecione ao menos um item.');
                return;
            }

            const rowIdsTodos = rowIdsNovas.concat(rowIdsAtual);
            const aplicarCoPorRow = coPorRowSelecionados(rowIdsTodos);

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
                        aplicar_custo_operacional_por_row: aplicarCoPorRow,
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
