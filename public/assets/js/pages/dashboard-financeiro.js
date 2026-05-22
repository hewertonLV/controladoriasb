(function () {
    'use strict';

    var config = window.dashboardFinanceiroConfig || {};
    var payload = window.dashboardFinanceiro;
    var chartDiario = null;
    var chartPizza = null;
    var chartRentabilidadeUnidades = null;
    var debounceTimer = null;
    var pollTimer = null;
    var emRequisicao = false;
    var atualizacaoFiltroPendente = false;
    var pausado = false;
    var monitoramentoAtivo = false;
    var ultimoPizzaFatias = null;

    var diarioEl = document.querySelector('#dashboard-financeiro-diario');
    var pizzaEl = document.querySelector('#dashboard-financeiro-pizza');
    var rentabilidadeUnidadesEl = document.querySelector('#dashboard-financeiro-rentabilidade-unidades');
    var statusEl = document.getElementById('dashboard-filtro-status');
    var monitorStatusEl = document.getElementById('dashboard-monitor-status');
    var periodoEl = document.getElementById('dashboard-periodo-label');
    var cardsEl = document.getElementById('dashboard-cards');
    var loadingDiario = document.getElementById('dashboard-chart-loading-diario');
    var loadingPizza = document.getElementById('dashboard-chart-loading-pizza');
    var loadingRentabilidadeUnidades = document.getElementById('dashboard-chart-loading-rentabilidade-unidades');
    var btnPausar = document.getElementById('dashboard-pausar');
    var btnRetomar = document.getElementById('dashboard-retomar');
    var btnBuscarMes = document.getElementById('dashboard-buscar-mes');
    var periodoTipoEl = document.getElementById('dashboard-periodo-tipo');
    var wrapMesEl = document.getElementById('dashboard-periodo-mes-wrap');
    var wrapDiaEl = document.getElementById('dashboard-periodo-dia-wrap');

    function fmtReais(valor) {
        return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtKg(valor) {
        return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
    }

    function fmtPct(valor) {
        if (valor === null || valor === undefined) {
            return '—';
        }
        return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    }

    function clsValor(valor) {
        if (valor > 0) {
            return 'text-success';
        }
        if (valor < 0) {
            return 'text-danger';
        }
        return 'text-muted';
    }

    function unidadesSelecionadas() {
        return Array.from(document.querySelectorAll('.dashboard-unidade-switch:checked'))
            .map(function (input) {
                return input.value;
            });
    }

    function setStatusUnidades(texto, tipo) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = texto;
        statusEl.className = 'badge ' + (tipo || 'bg-light text-muted');
    }

    function setStatusMonitor(html, tipo) {
        if (!monitorStatusEl) {
            return;
        }
        monitorStatusEl.className = 'badge ' + (tipo || 'bg-secondary-subtle text-secondary');
        monitorStatusEl.innerHTML = html;
    }

    function setLoading(ativo) {
        if (cardsEl) {
            cardsEl.classList.toggle('opacity-50', ativo);
        }
        if (loadingDiario) {
            loadingDiario.classList.toggle('d-none', !ativo);
        }
        if (loadingPizza) {
            loadingPizza.classList.toggle('d-none', !ativo);
        }
        if (loadingRentabilidadeUnidades) {
            loadingRentabilidadeUnidades.classList.toggle('d-none', !ativo);
        }
    }

    function atualizarCards(data) {
        var cards = data.cards || {};

        Object.keys(cards).forEach(function (key) {
            var item = cards[key];
            var reaisEl = document.querySelector('[data-card="' + key + '"][data-metric="reais"]');
            var kgEl = document.querySelector('[data-card="' + key + '"][data-metric="kg"]');
            var pctEl = document.querySelector('[data-card="' + key + '"][data-metric="percentual"]');

            if (reaisEl) {
                reaisEl.textContent = fmtReais(item.reais || 0);
                reaisEl.className = 'my-1 fw-bold dashboard-card-reais ' + clsValor(Number(item.reais || 0));
            }
            if (kgEl) {
                kgEl.textContent = fmtKg(item.kg || 0);
            }
            if (pctEl) {
                pctEl.textContent = fmtPct(item.percentual);
                pctEl.className = 'text-nowrap fw-semibold dashboard-card-pct ' + clsValor(Number(item.reais || 0));
            }
        });
    }

    function renderGraficoDiario(grafico) {
        if (!diarioEl || typeof ApexCharts === 'undefined') {
            return;
        }

        var dataColors = diarioEl.getAttribute('data-colors');
        var colors = dataColors ? dataColors.split(',') : ['#0acf97', '#777edd', '#fa5c7c', '#45bbe0'];

        var options = {
            series: [
                { name: 'Faturado (R$)', type: 'column', data: grafico.faturado || [] },
                { name: 'Doado (R$)', type: 'column', data: grafico.doado || [] },
                { name: 'Descartado (R$)', type: 'column', data: grafico.descartado || [] },
                { name: 'Vendido (kg)', type: 'line', data: grafico.vendido_kg || [] },
            ],
            chart: {
                height: 360,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            stroke: { width: [0, 0, 0, 3], curve: 'smooth' },
            plotOptions: {
                bar: { columnWidth: '42%', borderRadius: 4 },
            },
            dataLabels: { enabled: false },
            xaxis: { categories: grafico.categorias || [] },
            yaxis: [
                {
                    title: { text: 'R$' },
                    labels: {
                        formatter: function (val) {
                            return val != null
                                ? 'R$ ' + Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 0 })
                                : val;
                        },
                    },
                },
                {
                    opposite: true,
                    title: { text: 'kg' },
                    labels: {
                        formatter: function (val) {
                            return val != null
                                ? Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' kg'
                                : val;
                        },
                    },
                },
            ],
            colors: colors,
            tooltip: {
                shared: true,
                intersect: false,
            },
            legend: { position: 'bottom', horizontalAlign: 'center' },
        };

        if (chartDiario) {
            chartDiario.updateSeries(options.series);
            chartDiario.updateOptions({
                xaxis: options.xaxis,
            });
            return;
        }

        chartDiario = new ApexCharts(diarioEl, options);
        chartDiario.render();
    }

    function renderGraficoPizza(pizza) {
        if (!pizzaEl || typeof ApexCharts === 'undefined') {
            return;
        }

        var pizzaColorsAttr = pizzaEl.getAttribute('data-colors');
        var pizzaColors = pizzaColorsAttr ? pizzaColorsAttr.split(',') : ['#0acf97', '#fa5c7c', '#777edd'];

        var labels = (pizza || []).map(function (item) {
            var valor = Number(item.valor_exibicao || 0);
            var sinal = valor >= 0 ? '+' : '';
            return item.label + ' (' + sinal + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')';
        });
        var series = (pizza || []).map(function (item) {
            return Math.abs(Number(item.valor || 0));
        });

        var options = {
            chart: { height: 360, type: 'donut' },
            series: series,
            labels: labels,
            colors: pizzaColors,
            legend: { position: 'bottom' },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Number(val).toFixed(1) + '%';
                },
            },
        };

        if (chartPizza && ultimoPizzaFatias !== series.length) {
            chartPizza.destroy();
            chartPizza = null;
        }

        if (chartPizza) {
            chartPizza.updateSeries(options.series);
            chartPizza.updateOptions({
                labels: options.labels,
            });
            ultimoPizzaFatias = series.length;
            return;
        }

        chartPizza = new ApexCharts(pizzaEl, options);
        chartPizza.render();
        ultimoPizzaFatias = series.length;
    }

    function renderGraficoRentabilidadeUnidades(grafico) {
        if (!rentabilidadeUnidadesEl || typeof ApexCharts === 'undefined') {
            return;
        }

        var dataColors = rentabilidadeUnidadesEl.getAttribute('data-colors');
        var colors = dataColors ? dataColors.split(',') : ['#0acf97', '#777edd'];
        var categorias = grafico.categorias || [];
        var reais = grafico.reais || [];
        var percentual = grafico.percentual || [];

        if (categorias.length === 0) {
            if (chartRentabilidadeUnidades) {
                chartRentabilidadeUnidades.destroy();
                chartRentabilidadeUnidades = null;
            }
            rentabilidadeUnidadesEl.innerHTML = '<p class="text-muted text-center py-5 mb-0">Nenhuma unidade ativa ou sem movimentação no período.</p>';
            return;
        }

        if (rentabilidadeUnidadesEl.querySelector('p.text-muted')) {
            rentabilidadeUnidadesEl.innerHTML = '';
        }

        var options = {
            series: [
                { name: 'Rentabilidade (R$)', type: 'column', data: reais },
                { name: 'Margem (%)', type: 'line', data: percentual },
            ],
            chart: {
                height: 380,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: {
                bar: {
                    columnWidth: categorias.length <= 3 ? '40%' : '55%',
                    borderRadius: 4,
                    dataLabels: { position: 'top' },
                },
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0, 1],
                formatter: function (val, opts) {
                    if (opts.seriesIndex === 0) {
                        return fmtReais(val);
                    }
                    return fmtPct(val);
                },
                offsetY: -8,
                style: { fontSize: '11px' },
            },
            xaxis: {
                categories: categorias,
                labels: {
                    rotate: categorias.length > 4 ? -35 : 0,
                    trim: true,
                },
            },
            yaxis: [
                {
                    title: { text: 'R$' },
                    labels: {
                        formatter: function (val) {
                            return val != null
                                ? 'R$ ' + Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 0 })
                                : val;
                        },
                    },
                },
                {
                    opposite: true,
                    title: { text: '%' },
                    labels: {
                        formatter: function (val) {
                            return val != null
                                ? Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'
                                : val;
                        },
                    },
                },
            ],
            colors: colors,
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val, opts) {
                        if (opts.seriesIndex === 0) {
                            return fmtReais(val);
                        }
                        return fmtPct(val);
                    },
                },
            },
            legend: { position: 'bottom', horizontalAlign: 'center' },
        };

        if (chartRentabilidadeUnidades) {
            chartRentabilidadeUnidades.updateSeries(options.series);
            chartRentabilidadeUnidades.updateOptions({
                xaxis: options.xaxis,
            });
            return;
        }

        chartRentabilidadeUnidades = new ApexCharts(rentabilidadeUnidadesEl, options);
        chartRentabilidadeUnidades.render();
    }

    function atualizarStatusUnidades(data) {
        var qtd = (data.filtro_unidades || []).length;
        var total = (data.unidades_disponiveis || []).length;
        setStatusUnidades(
            qtd + ' de ' + total + ' unidade(s) ativa(s)',
            qtd > 0 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning'
        );
    }

    function aplicarPayload(data) {
        if (!data) {
            return;
        }

        if (data.proximo_poll_ms !== undefined) {
            delete data.proximo_poll_ms;
        }

        payload = data;
        window.dashboardFinanceiro = data;

        if (periodoEl && data.periodo) {
            periodoEl.textContent = data.periodo.label || '';
        }

        atualizarCards(data);
        renderGraficoDiario(data.grafico_diario || {});
        renderGraficoPizza(data.pizza_rentabilidade || []);
        renderGraficoRentabilidadeUnidades(data.grafico_rentabilidade_unidades || {});
        atualizarStatusUnidades(data);
    }

    function periodoTipoSelecionado() {
        return periodoTipoEl && periodoTipoEl.value === 'dia' ? 'dia' : 'mes';
    }

    function mesSelecionado() {
        var input = document.getElementById('dashboard-mes');
        return input && input.value ? input.value : new Date().toISOString().slice(0, 7);
    }

    function diaSelecionado() {
        var input = document.getElementById('dashboard-dia');
        return input && input.value ? input.value : new Date().toISOString().slice(0, 10);
    }

    function sincronizarCamposPeriodo() {
        var isDia = periodoTipoSelecionado() === 'dia';
        if (wrapMesEl) {
            wrapMesEl.classList.toggle('d-none', isDia);
        }
        if (wrapDiaEl) {
            wrapDiaEl.classList.toggle('d-none', !isDia);
        }
    }

    function montarUrlDados() {
        var url = new URL(config.dadosUrl, window.location.origin);

        if (periodoTipoSelecionado() === 'dia') {
            url.searchParams.set('dia', diaSelecionado());
        } else {
            url.searchParams.set('mes', mesSelecionado());
        }

        var selecionadas = unidadesSelecionadas();
        if (selecionadas.length === 0) {
            url.searchParams.set('sem_unidades', '1');
        } else {
            selecionadas.forEach(function (id) {
                url.searchParams.append('unidades[]', id);
            });
        }

        return url;
    }

    function agendar(ms) {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
        pollTimer = window.setTimeout(function () {
            executarAtualizacao(false);
        }, ms);
    }

    function executarAtualizacao(imediato, opcoes) {
        opcoes = opcoes || {};
        var porFiltro = !!opcoes.porFiltro;

        if (!config.dadosUrl || document.hidden) {
            return;
        }

        if (!porFiltro && pausado) {
            return;
        }

        if (emRequisicao) {
            if (porFiltro) {
                atualizacaoFiltroPendente = true;
            }
            return;
        }

        emRequisicao = true;
        setLoading(true);
        setStatusMonitor('<i class="ri-loader-4-line me-1"></i> Atualizando…', 'bg-info-subtle text-info');

        fetch(montarUrlDados().toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var intervalo = data.proximo_poll_ms || config.pollIntervalMs || 45000;
                aplicarPayload(data);
                if (monitoramentoAtivo && !pausado) {
                    setStatusMonitor('<i class="ri-refresh-line me-1"></i> Monitorando', 'bg-success-subtle text-success');
                    agendar(intervalo);
                }
            })
            .catch(function (error) {
                console.warn('[Dashboard financeira]', error);
                setStatusMonitor('<i class="ri-error-warning-line me-1"></i> Erro na consulta', 'bg-danger-subtle text-danger');
                if (monitoramentoAtivo && !pausado) {
                    agendar((config.pollIntervalMs || 45000) * 2);
                }
            })
            .finally(function () {
                emRequisicao = false;
                setLoading(false);

                if (atualizacaoFiltroPendente) {
                    atualizacaoFiltroPendente = false;
                    executarAtualizacao(true, { porFiltro: true });
                }
            });
    }

    function iniciarMonitoramento(imediato) {
        if (!config.dadosUrl) {
            return;
        }

        monitoramentoAtivo = true;
        pausado = false;
        if (btnPausar) {
            btnPausar.classList.remove('d-none');
        }
        if (btnRetomar) {
            btnRetomar.classList.add('d-none');
        }

        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }

        if (imediato) {
            executarAtualizacao(true);
        } else {
            var intervalo = config.pollIntervalMs || 45000;
            setStatusMonitor('<i class="ri-refresh-line me-1"></i> Monitorando', 'bg-success-subtle text-success');
            agendar(intervalo);
        }
    }

    function pausar() {
        pausado = true;
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
        if (btnPausar) {
            btnPausar.classList.add('d-none');
        }
        if (btnRetomar) {
            btnRetomar.classList.remove('d-none');
        }
        setStatusMonitor('<i class="ri-pause-circle-line me-1"></i> Pausado', 'bg-secondary-subtle text-secondary');
    }

    function retomar() {
        pausado = false;
        if (btnPausar) {
            btnPausar.classList.remove('d-none');
        }
        if (btnRetomar) {
            btnRetomar.classList.add('d-none');
        }
        if (monitoramentoAtivo) {
            executarAtualizacao(true);
        }
    }

    function agendarAtualizacaoFiltro() {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }
        debounceTimer = window.setTimeout(function () {
            executarAtualizacao(true, { porFiltro: true });
        }, 350);
    }

    function initSwitches() {
        document.querySelectorAll('.dashboard-unidade-switch').forEach(function (input) {
            input.addEventListener('change', agendarAtualizacaoFiltro);
        });
    }

    function initPeriodo() {
        sincronizarCamposPeriodo();
        if (periodoTipoEl) {
            periodoTipoEl.addEventListener('change', sincronizarCamposPeriodo);
        }
    }

    if (btnBuscarMes) {
        btnBuscarMes.addEventListener('click', function () {
            iniciarMonitoramento(true);
        });
    }

    if (btnPausar) {
        btnPausar.addEventListener('click', pausar);
    }

    if (btnRetomar) {
        btnRetomar.addEventListener('click', retomar);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
            setStatusMonitor('<i class="ri-eye-off-line me-1"></i> Aba em segundo plano', 'bg-secondary-subtle text-secondary');
        } else if (!pausado && monitoramentoAtivo) {
            executarAtualizacao(true);
        }
    });

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
    });

    if (payload) {
        aplicarPayload(payload);
    }

    initSwitches();
    initPeriodo();

    if (config.dadosUrl) {
        iniciarMonitoramento(false);
    }
})();
