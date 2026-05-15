@props([
    'queue' => 'exportacao',
    'tableRootId' => 'table-root',
])

<div class="card border-0 shadow-sm d-none mb-3"
     id="card-exportacao-pdf"
     data-state="AGUARDANDO"
     role="status"
     aria-live="polite">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="flex-shrink-0">
                <span class="avatar-sm d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
                      id="pdf-status-icone-wrapper">
                    <i class="ri-loader-4-line fs-22 text-primary"
                       id="pdf-status-icone"
                       aria-hidden="true"></i>
                </span>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h5 class="header-title mb-0" id="pdf-status-titulo">Aguardando worker da fila...</h5>
                    <span class="badge bg-secondary-subtle text-secondary d-inline-flex align-items-center gap-1"
                          id="pdf-status-badge">
                        <span class="spinner-border spinner-border-sm d-none"
                              id="pdf-status-spinner"
                              role="status"
                              aria-hidden="true"
                              style="width: 0.7rem; height: 0.7rem; border-width: 0.12em;"></span>
                        <span id="pdf-status-badge-texto">AGUARDANDO</span>
                    </span>
                    <span class="badge bg-light text-muted d-none" id="pdf-status-contador">Aguardando há 0s</span>
                </div>
                <p class="text-muted mb-1" id="pdf-status-texto">
                    O PDF foi solicitado e aguarda o worker iniciar o processamento.
                </p>
                <small class="text-muted d-none" id="pdf-status-hint">
                    Se permanecer muito tempo neste estado, verifique se o worker
                    <code>{{ $queue }}</code> está rodando.
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2" id="pdf-status-acoes">
                <a href="#"
                   class="btn btn-success d-none"
                   id="btn-baixar-pdf"
                   target="_blank"
                   rel="noopener">
                    <i class="ri-download-2-line me-1"></i> Baixar PDF
                </a>
                <button type="button"
                        class="btn btn-outline-primary d-none"
                        id="btn-novo-pdf">
                    <i class="ri-refresh-line me-1"></i> Gerar novo PDF
                </button>
                <button type="button"
                        class="btn btn-outline-danger d-none"
                        id="btn-tentar-pdf">
                    <i class="ri-restart-line me-1"></i> Tentar novamente
                </button>
                <button type="button"
                        class="btn btn-link text-muted d-none"
                        id="btn-fechar-pdf"
                        aria-label="Fechar aviso">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>
        <div class="progress mt-3" style="height: 10px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 id="pdf-progress-bar"
                 role="progressbar"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="100"
                 style="width: 100%;">
            </div>
        </div>

        <div class="alert alert-warning mt-3 mb-0 py-2 px-3 d-none"
             id="pdf-aviso-15s"
             role="alert">
            <i class="ri-alert-line me-1 align-middle"></i>
            A exportação ainda está aguardando processamento. Verifique se o worker de exportação está ativo.
        </div>

        <div class="mt-2 small text-muted d-none" id="pdf-aviso-60s">
            <span class="text-uppercase fw-semibold text-muted small">Comando para desenvolvimento</span>
            <pre class="bg-light border rounded p-2 mt-1 mb-0"
                 style="font-size: .75rem; white-space: pre-wrap; word-break: break-word;"><code>php artisan queue:work --queue={{ $queue }},default --tries=1 --timeout=900</code></pre>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const tableRootId = @json($tableRootId);

    const btnGerar = document.getElementById('btn-gerar-pdf');
    const spinnerGerar = document.getElementById('spinner-gerar-pdf');
    const card = document.getElementById('card-exportacao-pdf');
    if (!btnGerar || !card) {
        return;
    }

    const iconeWrapper = document.getElementById('pdf-status-icone-wrapper');
    const icone = document.getElementById('pdf-status-icone');
    const titulo = document.getElementById('pdf-status-titulo');
    const texto = document.getElementById('pdf-status-texto');
    const hint = document.getElementById('pdf-status-hint');
    const badge = document.getElementById('pdf-status-badge');
    const badgeTexto = document.getElementById('pdf-status-badge-texto');
    const badgeSpinner = document.getElementById('pdf-status-spinner');
    const contador = document.getElementById('pdf-status-contador');
    const aviso15 = document.getElementById('pdf-aviso-15s');
    const aviso60 = document.getElementById('pdf-aviso-60s');
    const barra = document.getElementById('pdf-progress-bar');
    const btnBaixar = document.getElementById('btn-baixar-pdf');
    const btnNovo = document.getElementById('btn-novo-pdf');
    const btnTentar = document.getElementById('btn-tentar-pdf');
    const btnFechar = document.getElementById('btn-fechar-pdf');

    const POLL_INTERVAL_MS = 1500;
    const MAX_NETWORK_RETRIES = 5;
    const AVISO_15_SEGUNDOS = 15;
    const AVISO_60_SEGUNDOS = 60;

    let pollTimer = null;
    let tickerTimer = null;
    let networkRetries = 0;
    let exportacaoAtiva = false;
    let aguardandoDesde = null;

    const ESTADOS = {
        AGUARDANDO: {
            titulo: 'Aguardando worker da fila...',
            mensagem: 'O PDF foi solicitado e aguarda o worker iniciar o processamento.',
            badgeClass: 'badge d-inline-flex align-items-center gap-1 bg-secondary-subtle text-secondary',
            barraClass: 'progress-bar progress-bar-striped progress-bar-animated bg-primary',
            iconeClass: 'ri-time-line fs-22 text-secondary',
            wrapperClass: 'avatar-sm d-inline-flex align-items-center justify-content-center rounded-circle bg-light',
            spinner: true,
            showHint: true,
            showTicker: true,
        },
        PROCESSANDO: {
            titulo: 'Gerando PDF...',
            mensagem: 'O PDF está sendo gerado em background.',
            badgeClass: 'badge d-inline-flex align-items-center gap-1 bg-primary-subtle text-primary',
            barraClass: 'progress-bar progress-bar-striped progress-bar-animated bg-primary',
            iconeClass: 'ri-loader-4-line fs-22 text-primary',
            wrapperClass: 'avatar-sm d-inline-flex align-items-center justify-content-center rounded-circle bg-light',
            spinner: true,
            showHint: false,
            showTicker: false,
        },
        CONCLUIDO: {
            titulo: 'PDF pronto',
            mensagem: 'O relatório foi gerado com sucesso.',
            badgeClass: 'badge d-inline-flex align-items-center gap-1 bg-success-subtle text-success',
            barraClass: 'progress-bar bg-success',
            iconeClass: 'ri-checkbox-circle-line fs-22 text-success',
            wrapperClass: 'avatar-sm d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle',
            spinner: false,
            showHint: false,
            showTicker: false,
        },
        FALHOU: {
            titulo: 'Falha ao gerar PDF',
            mensagem: 'O PDF não pôde ser gerado.',
            badgeClass: 'badge d-inline-flex align-items-center gap-1 bg-danger-subtle text-danger',
            barraClass: 'progress-bar bg-danger',
            iconeClass: 'ri-error-warning-line fs-22 text-danger',
            wrapperClass: 'avatar-sm d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle',
            spinner: false,
            showHint: false,
            showTicker: false,
        },
    };

    function currentFilters() {
        const root = document.getElementById(tableRootId);
        const params = new URLSearchParams();
        if (!root) return params;

        const search = root.querySelector('[data-table-search]');
        const perPage = root.querySelector('[data-table-per-page]');
        const sort = root.querySelector('[data-table-sort]');
        const direction = root.querySelector('[data-table-direction]');
        const filters = root.querySelectorAll('[data-table-filter]');

        if (search && search.value.trim() !== '') params.set('search', search.value.trim());
        if (perPage && perPage.value !== '') params.set('per_page', perPage.value);
        if (sort && sort.value !== '') params.set('sort', sort.value);
        if (direction && direction.value !== '') params.set('direction', direction.value);
        filters.forEach((el) => {
            const name = el.getAttribute('name') || el.dataset.tableFilter;
            const value = (el.value || '').toString();
            if (name && value !== '') params.set(name, value);
        });

        return params;
    }

    function setBotaoGerarOcupado(ocupado) {
        btnGerar.disabled = ocupado;
        spinnerGerar?.classList.toggle('d-none', !ocupado);
    }

    function aplicarEstado(estado, overrides = {}) {
        const config = ESTADOS[estado] || ESTADOS.AGUARDANDO;

        card.classList.remove('d-none');
        card.dataset.state = estado;

        titulo.textContent = overrides.titulo || config.titulo;
        texto.textContent = overrides.mensagem || config.mensagem;
        badgeTexto.textContent = estado;
        badge.className = config.badgeClass;
        badgeSpinner.classList.toggle('d-none', !config.spinner);

        hint.classList.toggle('d-none', !config.showHint);

        icone.className = config.iconeClass;
        iconeWrapper.className = config.wrapperClass;

        barra.className = config.barraClass;
        barra.style.width = '100%';
        barra.setAttribute('aria-valuenow', '100');

        btnBaixar.classList.toggle('d-none', estado !== 'CONCLUIDO' || !overrides.downloadUrl);
        btnNovo.classList.toggle('d-none', estado !== 'CONCLUIDO');
        btnTentar.classList.toggle('d-none', estado !== 'FALHOU');
        btnFechar.classList.toggle('d-none', estado !== 'CONCLUIDO' && estado !== 'FALHOU');

        if (estado === 'CONCLUIDO' && overrides.downloadUrl) {
            btnBaixar.href = overrides.downloadUrl;
        }

        if (config.showTicker) {
            contador.classList.remove('d-none');
            atualizarContador();
        } else {
            contador.classList.add('d-none');
            aviso15.classList.add('d-none');
            aviso60.classList.add('d-none');
        }

        const finalizado = estado === 'CONCLUIDO' || estado === 'FALHOU';
        if (finalizado) {
            stopTicker();
        }

        setBotaoGerarOcupado(!finalizado && exportacaoAtiva);
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function stopTicker() {
        if (tickerTimer) {
            clearInterval(tickerTimer);
            tickerTimer = null;
        }
    }

    function iniciarTicker(createdAtIso) {
        stopTicker();
        aguardandoDesde = parseCreatedAt(createdAtIso);
        aviso15.classList.add('d-none');
        aviso60.classList.add('d-none');
        contador.classList.remove('d-none');
        atualizarContador();
        tickerTimer = setInterval(atualizarContador, 1000);
    }

    function parseCreatedAt(iso) {
        if (iso) {
            const ts = Date.parse(iso);
            if (!Number.isNaN(ts)) {
                return ts;
            }
        }
        return Date.now();
    }

    function atualizarContador() {
        if (aguardandoDesde === null) {
            aguardandoDesde = Date.now();
        }
        const segundos = Math.max(0, Math.floor((Date.now() - aguardandoDesde) / 1000));
        contador.textContent = 'Aguardando há ' + segundos + 's';
        aviso15.classList.toggle('d-none', segundos < AVISO_15_SEGUNDOS);
        aviso60.classList.toggle('d-none', segundos < AVISO_60_SEGUNDOS);
    }

    function finalizar() {
        stopPolling();
        stopTicker();
        exportacaoAtiva = false;
        aguardandoDesde = null;
        setBotaoGerarOcupado(false);
    }

    async function poll(statusUrl) {
        try {
            const resp = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!resp.ok) {
                let data = {};
                try { data = await resp.json(); } catch (_) {}
                aplicarEstado('FALHOU', { mensagem: data.message || 'Falha ao consultar status da exportação.' });
                finalizar();
                return;
            }

            const data = await resp.json();
            networkRetries = 0;

            if (data.status === 'CONCLUIDO') {
                const total = data.total_registros ? ` (${data.total_registros} registro${data.total_registros === 1 ? '' : 's'})` : '';
                aplicarEstado('CONCLUIDO', {
                    mensagem: (data.mensagem || ESTADOS.CONCLUIDO.mensagem) + total,
                    downloadUrl: data.download_url,
                });
                finalizar();
                return;
            }

            if (data.status === 'FALHOU') {
                aplicarEstado('FALHOU', {
                    mensagem: data.erro_mensagem || data.mensagem || ESTADOS.FALHOU.mensagem,
                });
                finalizar();
                return;
            }

            if (data.status === 'PROCESSANDO') {
                stopTicker();
                aplicarEstado('PROCESSANDO', { mensagem: data.mensagem });
            } else {
                if (data.created_at && aguardandoDesde === null) {
                    aguardandoDesde = parseCreatedAt(data.created_at);
                }
                aplicarEstado('AGUARDANDO', { mensagem: data.mensagem });
            }
            pollTimer = setTimeout(() => poll(statusUrl), POLL_INTERVAL_MS);
        } catch (err) {
            networkRetries += 1;
            if (networkRetries >= MAX_NETWORK_RETRIES) {
                aplicarEstado('FALHOU', {
                    mensagem: 'Não foi possível consultar o status da exportação. Verifique sua conexão e tente novamente.',
                });
                finalizar();
                return;
            }
            pollTimer = setTimeout(() => poll(statusUrl), POLL_INTERVAL_MS * Math.min(networkRetries, 3));
        }
    }

    async function iniciarExportacao() {
        if (exportacaoAtiva) {
            return;
        }
        stopPolling();
        stopTicker();
        networkRetries = 0;
        exportacaoAtiva = true;
        aguardandoDesde = Date.now();

        aplicarEstado('AGUARDANDO', { mensagem: 'Enviando filtros atuais para a fila...' });
        setBotaoGerarOcupado(true);

        const params = currentFilters();

        try {
            const resp = await fetch(btnGerar.dataset.pdfIniciarUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(Object.fromEntries(params.entries())),
            });

            let data = {};
            try { data = await resp.json(); } catch (_) {}

            if (!resp.ok) {
                aplicarEstado('FALHOU', { mensagem: data.message || 'Não foi possível iniciar a exportação.' });
                finalizar();
                return;
            }

            iniciarTicker(data.created_at);
            aplicarEstado(data.status || 'AGUARDANDO', { mensagem: data.mensagem });
            poll(data.urls.status);
        } catch (err) {
            aplicarEstado('FALHOU', { mensagem: 'Falha de comunicação: ' + (err.message || err) });
            finalizar();
        }
    }

    btnGerar.addEventListener('click', (e) => {
        e.preventDefault();
        iniciarExportacao();
    });

    btnNovo?.addEventListener('click', () => iniciarExportacao());
    btnTentar?.addEventListener('click', () => iniciarExportacao());
    btnFechar?.addEventListener('click', () => card.classList.add('d-none'));

    window.addEventListener('beforeunload', () => {
        stopPolling();
        stopTicker();
    });
})();
</script>
@endpush
