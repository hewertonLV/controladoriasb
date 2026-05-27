(function () {
    'use strict';

    const config = window.__OLHO_DE_FABIO__;
    if (!config?.pollUrl) {
        return;
    }

    const lista = document.getElementById('olho-de-fabio-lista');
    const vazio = document.getElementById('olho-de-fabio-vazio');
    const totalEl = document.getElementById('olho-de-fabio-total');
    const statusEl = document.getElementById('olho-de-fabio-status');
    const periodoEl = document.getElementById('olho-de-fabio-periodo-label');
    const btnPausar = document.getElementById('olho-de-fabio-pausar');
    const btnRetomar = document.getElementById('olho-de-fabio-retomar');
    const btnLimpar = document.getElementById('olho-de-fabio-limpar');
    const btnBuscar = document.getElementById('olho-de-fabio-buscar');
    const inputMes = document.getElementById('olho-de-fabio-mes');

    const vistos = new Set();
    const alertasNaTela = [];
    let since = null;
    let pollTimer = null;
    let pausado = false;
    let emRequisicao = false;
    let monitoramentoAtivo = false;

    function mesSelecionado() {
        return inputMes && inputMes.value
            ? inputMes.value
            : new Date().toISOString().slice(0, 7);
    }

    function setStatus(texto, classe) {
        if (!statusEl) {
            return;
        }
        statusEl.className = 'badge ' + classe;
        statusEl.innerHTML = texto;
    }

    function atualizarPeriodo(periodo) {
        if (periodoEl && periodo?.label) {
            periodoEl.textContent = periodo.label;
        }
    }

    function atualizarContador() {
        if (totalEl) {
            totalEl.textContent = String(alertasNaTela.length);
        }
        if (vazio && lista) {
            const temItens = alertasNaTela.length > 0;
            vazio.classList.toggle('d-none', temItens);
            lista.classList.toggle('d-none', !temItens);
        }
    }

    function severidadeClasse(severidade) {
        if (severidade === 'warning') {
            return 'list-group-item-warning';
        }
        if (severidade === 'danger') {
            return 'list-group-item-danger';
        }

        return '';
    }

    function prependAlerta(alerta) {
        if (!lista || vistos.has(alerta.id)) {
            return;
        }

        vistos.add(alerta.id);
        alertasNaTela.unshift(alerta);

        const item = document.createElement(alerta.url ? 'a' : 'div');
        item.className = 'list-group-item ' + severidadeClasse(alerta.severidade);
        if (alerta.url) {
            item.href = alerta.url;
            item.classList.add('list-group-item-action');
        }

        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-semibold">${alerta.titulo}</div>
                    <div class="text-muted fs-13">${alerta.mensagem}</div>
                    <small class="text-muted">${alerta.categoria} · ${alerta.data_movimentacao}</small>
                </div>
                <span class="badge bg-dark-subtle text-dark text-uppercase">${alerta.severidade}</span>
            </div>
        `;

        lista.prepend(item);
        atualizarContador();
    }

    function limparLista() {
        alertasNaTela.length = 0;
        vistos.clear();
        if (lista) {
            lista.innerHTML = '';
        }
        atualizarContador();
    }

    async function executarPoll(cargaInicial) {
        if (pausado || emRequisicao || document.hidden) {
            return;
        }

        emRequisicao = true;
        setStatus('<i class="ri-loader-4-line me-1"></i> Atualizando…', 'bg-info-subtle text-info');

        try {
            const url = new URL(config.pollUrl, window.location.origin);
            url.searchParams.set('mes', mesSelecionado());

            if (cargaInicial) {
                url.searchParams.set('carga_inicial', '1');
            } else if (since) {
                url.searchParams.set('since', since);
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const payload = await response.json();

            if (payload.periodo) {
                atualizarPeriodo(payload.periodo);
            }

            if (payload.server_time) {
                since = payload.server_time;
            }

            (payload.alertas || []).forEach(prependAlerta);

            const intervalo = payload.proximo_poll_ms || config.pollIntervalMs;
            setStatus('<i class="ri-eye-line me-1"></i> Monitorando', 'bg-success-subtle text-success');
            agendar(intervalo);
        } catch (error) {
            console.warn('[Olho de Fabio]', error);
            setStatus('<i class="ri-error-warning-line me-1"></i> Erro na consulta', 'bg-danger-subtle text-danger');
            agendar(config.pollIntervalMs * 2);
        } finally {
            emRequisicao = false;
        }
    }

    function poll() {
        executarPoll(false);
    }

    function agendar(ms) {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
        pollTimer = window.setTimeout(poll, ms);
    }

    function buscar() {
        limparLista();
        since = null;
        pausado = false;
        monitoramentoAtivo = true;
        btnPausar?.classList.remove('d-none');
        btnRetomar?.classList.add('d-none');
        executarPoll(true);
    }

    function pausar() {
        pausado = true;
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
        btnPausar?.classList.add('d-none');
        btnRetomar?.classList.remove('d-none');
        setStatus('<i class="ri-pause-circle-line me-1"></i> Pausado', 'bg-secondary-subtle text-secondary');
    }

    function retomar() {
        pausado = false;
        btnPausar?.classList.remove('d-none');
        btnRetomar?.classList.add('d-none');
        if (monitoramentoAtivo) {
            poll();
        }
    }

    btnPausar?.addEventListener('click', pausar);
    btnRetomar?.addEventListener('click', retomar);
    btnBuscar?.addEventListener('click', buscar);

    btnLimpar?.addEventListener('click', limparLista);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
            setStatus('<i class="ri-eye-off-line me-1"></i> Aba em segundo plano', 'bg-secondary-subtle text-secondary');
        } else if (!pausado && monitoramentoAtivo) {
            poll();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
    });

    setStatus('<i class="ri-search-line me-1"></i> Clique em Buscar', 'bg-secondary-subtle text-secondary');
})();
