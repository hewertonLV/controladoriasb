@extends('layouts.app')

@section('title', 'Importar lojas — '.$carteira->nome)
@section('page-title', 'Importar lojas — '.$carteira->nome)

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Importação por planilha Excel</h4>
                <p class="text-muted mb-0">
                    Layout (linha 1 = cabeçalho):
                    <code>A</code> ID CIGAM do cliente — um cliente por linha.
                    Modelo: <code>planilhas/carteira_lojas_vinculo.xlsx</code>
                </p>
                <p class="mb-0 small">
                    <strong>Carteira:</strong> {{ $carteira->nome }} ·
                    <strong>Faturamento:</strong> {{ $carteira->unidadeFaturamento?->nome ?? '—' }}
                </p>
            </div>
            <a href="{{ route('admin.captacao.carteiras.edit', $carteira) }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar para editar carteira
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
            <p class="text-muted small mt-2 mb-0">
                Importação <strong>aditiva</strong>: vincula novas lojas sem remover as já marcadas na carteira.
                Lojas de outra carteira ou de outro faturamento aparecem como erro.
            </p>
        </div>
    </div>

    @include('admin.captacao.carteiras._importar-lojas-progresso')
@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const urlIniciar = @json(route('admin.captacao.carteiras.importar-lojas.iniciar', $carteira));
    const urlVoltar = @json(route('admin.captacao.carteiras.edit', $carteira));

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
    let pollTimer = null;
    const POLL_INTERVAL_MS = 1500;

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
                <td>${escapeHtml(item.codigo || item.dados?.codigo || '')}</td>
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
                <td>${escapeHtml(item.codigo || '')}</td>
                <td>${escapeHtml(item.cliente_nome || '')}</td>
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
                <td>${escapeHtml(item.codigo || '')}</td>
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
        renderNovas(data.novas || []);
        renderSem(data.sem_alteracoes || []);
        renderErros(data.erros || []);
        resultado.classList.remove('d-none');
        atualizarResumoSelecao();
        if ((data.novas || []).length === 0) {
            showAlerta('info', 'Nenhuma loja nova para vincular. Revise «Já na carteira» ou «Erros».');
        }
    }

    function schedulePoll() { pollTimer = setTimeout(poll, POLL_INTERVAL_MS); }
    function pararPolling() { if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; } }

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
            showAlerta('warning', 'Selecione ao menos uma loja.');
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
                showAlerta('danger', escapeHtml(data.message || 'Erro ao vincular.'));
                return;
            }
            const r = data.resumo || {};
            document.getElementById('resumo-criadas').textContent = r.lojas_vinculadas || 0;
            document.getElementById('resumo-ignoradas').textContent = r.ignoradas || 0;
            if (data.redirect && btnVoltarLista) btnVoltarLista.href = data.redirect;
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

    if (btnVoltarLista) btnVoltarLista.href = urlVoltar;
    window.addEventListener('beforeunload', pararPolling);
})();
</script>
@endpush
