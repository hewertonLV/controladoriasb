(function () {
    'use strict';

    var config = window.dashboardFinanceiroConfig || {};
    var payload = window.dashboardFinanceiro;
    var chartDiario = null;
    var chartPizza = null;
    var chartRentabilidadeUnidades = null;
    var debounceTimer = null;
    var emRequisicao = false;

    var diarioEl = document.querySelector('#dashboard-financeiro-diario');
    var pizzaEl = document.querySelector('#dashboard-financeiro-pizza');
    var rentabilidadeUnidadesEl = document.querySelector('#dashboard-financeiro-rentabilidade-unidades');
    var statusEl = document.getElementById('dashboard-filtro-status');
    var periodoEl = document.getElementById('dashboard-periodo-label');
    var cardsEl = document.getElementById('dashboard-cards');
    var loadingDiario = document.getElementById('dashboard-chart-loading-diario');
    var loadingPizza = document.getElementById('dashboard-chart-loading-pizza');
    var loadingRentabilidadeUnidades = document.getElementById('dashboard-chart-loading-rentabilidade-unidades');

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

    function setStatus(texto, tipo) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = texto;
        statusEl.className = 'badge ' + (tipo || 'bg-light text-muted');
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
            chartDiario.updateOptions({
                series: options.series,
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

        if (chartPizza) {
            chartPizza.updateOptions({
                series: options.series,
                labels: options.labels,
            });
            return;
        }

        chartPizza = new ApexCharts(pizzaEl, options);
        chartPizza.render();
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
            chartRentabilidadeUnidades.updateOptions({
                series: options.series,
                xaxis: options.xaxis,
            });
            return;
        }

        chartRentabilidadeUnidades = new ApexCharts(rentabilidadeUnidadesEl, options);
        chartRentabilidadeUnidades.render();
    }

    function aplicarPayload(data) {
        payload = data;
        window.dashboardFinanceiro = data;

        if (periodoEl && data.periodo) {
            periodoEl.textContent = data.periodo.label || '';
        }

        atualizarCards(data);
        renderGraficoDiario(data.grafico_diario || {});
        renderGraficoPizza(data.pizza_rentabilidade || []);
        renderGraficoRentabilidadeUnidades(data.grafico_rentabilidade_unidades || {});

        var qtd = (data.filtro_unidades || []).length;
        var total = (data.unidades_disponiveis || []).length;
        setStatus(qtd + ' de ' + total + ' unidade(s) ativa(s)', qtd > 0 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning');
    }

    function mesSelecionado() {
        var input = document.getElementById('dashboard-mes');
        return input && input.value ? input.value : new Date().toISOString().slice(0, 7);
    }

    function buscarDados() {
        if (!config.dadosUrl || emRequisicao) {
            return;
        }

        var selecionadas = unidadesSelecionadas();
        var url = new URL(config.dadosUrl, window.location.origin);
        url.searchParams.set('mes', mesSelecionado());

        if (selecionadas.length === 0) {
            url.searchParams.set('sem_unidades', '1');
        } else {
            selecionadas.forEach(function (id) {
                url.searchParams.append('unidades[]', id);
            });
        }

        emRequisicao = true;
        setLoading(true);
        setStatus('Atualizando…', 'bg-info-subtle text-info');

        fetch(url.toString(), {
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
                aplicarPayload(data);
            })
            .catch(function () {
                setStatus('Erro ao atualizar', 'bg-danger-subtle text-danger');
            })
            .finally(function () {
                emRequisicao = false;
                setLoading(false);
            });
    }

    function agendarAtualizacao() {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }
        debounceTimer = window.setTimeout(buscarDados, 350);
    }

    function initSwitches() {
        document.querySelectorAll('.dashboard-unidade-switch').forEach(function (input) {
            input.addEventListener('change', agendarAtualizacao);
        });
    }

    var btnBuscar = document.getElementById('dashboard-buscar-mes');
    if (btnBuscar) {
        btnBuscar.addEventListener('click', buscarDados);
    }

    if (payload) {
        aplicarPayload(payload);
    }

    initSwitches();
})();
