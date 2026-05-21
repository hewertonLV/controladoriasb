(function () {
    var payload = window.dashboardFinanceiro;
    if (!payload || typeof ApexCharts === 'undefined') {
        return;
    }

    var colorsDefault = ['#0acf97', '#777edd', '#fa5c7c', '#45bbe0'];
    var diarioEl = document.querySelector('#dashboard-financeiro-diario');
    var pizzaEl = document.querySelector('#dashboard-financeiro-pizza');
    var grafico = payload.grafico_diario || {};
    var pizza = payload.pizza_rentabilidade || [];

    if (diarioEl) {
        var dataColors = diarioEl.getAttribute('data-colors');
        var colors = dataColors ? dataColors.split(',') : colorsDefault;

        var optionsDiario = {
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
                bar: {
                    columnWidth: '42%',
                    borderRadius: 4,
                },
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: grafico.categorias || [],
            },
            yaxis: [
                {
                    title: { text: 'R$' },
                    labels: {
                        formatter: function (val) {
                            return val != null ? 'R$ ' + Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : val;
                        },
                    },
                },
                {
                    opposite: true,
                    title: { text: 'kg' },
                    labels: {
                        formatter: function (val) {
                            return val != null ? Number(val).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' kg' : val;
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
                        if (val == null) {
                            return val;
                        }
                        if (opts.seriesIndex === 3) {
                            return Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
                        }
                        return 'R$ ' + Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                },
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
            },
        };

        new ApexCharts(diarioEl, optionsDiario).render();
    }

    if (pizzaEl) {
        var pizzaColorsAttr = pizzaEl.getAttribute('data-colors');
        var pizzaColors = pizzaColorsAttr ? pizzaColorsAttr.split(',') : ['#0acf97', '#fa5c7c', '#777edd'];

        var labels = pizza.map(function (item) {
            var valor = Number(item.valor_exibicao || 0);
            var sinal = valor >= 0 ? '+' : '';
            return item.label + ' (' + sinal + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')';
        });
        var series = pizza.map(function (item) {
            return Number(item.valor || 0);
        });

        var optionsPizza = {
            chart: {
                height: 360,
                type: 'donut',
            },
            series: series,
            labels: labels,
            colors: pizzaColors,
            legend: {
                position: 'bottom',
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Number(val).toFixed(1) + '%';
                },
            },
            tooltip: {
                y: {
                    formatter: function (_val, opts) {
                        var item = pizza[opts.seriesIndex];
                        if (!item) {
                            return '';
                        }
                        var exibicao = Number(item.valor_exibicao || 0);
                        return 'R$ ' + exibicao.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                },
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: { height: 280 },
                    legend: { position: 'bottom' },
                },
            }],
        };

        new ApexCharts(pizzaEl, optionsPizza).render();
    }
})();
