(function () {
    'use strict';

    const config = window.__FRETES_CALENDARIO__;
    if (!config?.eventosUrl) {
        return;
    }

    const elCalendario = document.getElementById('fretes-calendario');
    const inputMes = document.getElementById('fretes-calendario-mes');
    const btnBuscar = document.getElementById('fretes-calendario-buscar');
    const elLoading = document.getElementById('fretes-calendario-loading');
    const elPeriodo = document.getElementById('fretes-calendario-periodo-label');
    const elTotal = document.getElementById('fretes-calendario-total');
    const modalEl = document.getElementById('frete-calendario-modal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    let calendar = null;
    let emRequisicao = false;

    function mesSelecionado() {
        return inputMes && inputMes.value
            ? inputMes.value
            : config.mesInicial || new Date().toISOString().slice(0, 7);
    }

    function setLoading(ativo) {
        if (!elLoading) {
            return;
        }
        elLoading.classList.toggle('d-none', !ativo);
        elLoading.classList.toggle('d-flex', ativo);
    }

    function atualizarResumo(payload) {
        if (elPeriodo && payload?.label) {
            elPeriodo.textContent = payload.label;
        }
        if (elTotal) {
            elTotal.textContent = String((payload?.eventos || []).length);
        }
    }

    function abrirModal(evento) {
        if (!modal) {
            return;
        }

        const props = evento.extendedProps || {};

        document.getElementById('frete-calendario-modal-titulo').textContent = evento.title || 'Frete';
        document.getElementById('frete-calendario-modal-situacao').textContent =
            props.situacao_label || props.situacao || '—';
        document.getElementById('frete-calendario-modal-valor').textContent = props.valor ? 'R$ ' + props.valor : '—';
        document.getElementById('frete-calendario-modal-fruta-kg').textContent = props.valor_fruta_kg ? 'R$ ' + props.valor_fruta_kg : '—';
        document.getElementById('frete-calendario-modal-veiculo').textContent = props.veiculo || '—';
        document.getElementById('frete-calendario-modal-criado').textContent = props.criado_em || '—';
        document.getElementById('frete-calendario-modal-descricao').textContent = props.descricao || '—';

        const linkEditar = document.getElementById('frete-calendario-modal-editar');
        if (linkEditar) {
            if (props.editar_url) {
                linkEditar.href = props.editar_url;
                linkEditar.classList.remove('d-none');
            } else {
                linkEditar.classList.add('d-none');
            }
        }

        modal.show();
    }

    async function carregarEventos() {
        if (emRequisicao || !calendar) {
            return;
        }

        emRequisicao = true;
        setLoading(true);

        try {
            const url = new URL(config.eventosUrl, window.location.origin);
            url.searchParams.set('mes', mesSelecionado());

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
            const mes = payload.mes || mesSelecionado();

            calendar.gotoDate(mes + '-01');
            calendar.removeAllEvents();
            calendar.addEventSource(payload.eventos || []);
            atualizarResumo(payload);
        } catch (error) {
            console.warn('[Calendário Fretes]', error);
        } finally {
            emRequisicao = false;
            setLoading(false);
        }
    }

    function initCalendario() {
        if (!elCalendario || typeof FullCalendar === 'undefined') {
            return;
        }

        const mes = config.mesInicial || mesSelecionado();

        calendar = new FullCalendar.Calendar(elCalendario, {
            locale: 'pt-br',
            themeSystem: 'bootstrap',
            bootstrapFontAwesome: false,
            initialView: 'dayGridMonth',
            initialDate: mes + '-01',
            height: window.innerHeight - 220,
            handleWindowResize: true,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listMonth',
            },
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                list: 'Lista',
                prev: 'Anterior',
                next: 'Próximo',
            },
            noEventsText: 'Nenhum frete neste período',
            editable: false,
            selectable: false,
            events: [],
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                abrirModal(info.event);
            },
        });

        calendar.render();
        carregarEventos();
    }

    btnBuscar?.addEventListener('click', carregarEventos);

    inputMes?.addEventListener('change', carregarEventos);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCalendario);
    } else {
        initCalendario();
    }
})();
