/**
 * DataTables admin padrão (copiar, imprimir, busca client-side).
 * Sincroniza filtros ocultos para exportação PDF assíncrona (data-table-*).
 */
(function (window) {
    'use strict';

    const TOAST_DURATION_MS = 8000;
    const TOAST_HOST_ID = 'admin-datatable-toast-host';

    function isDebugEnabled() {
        return window.DEBUG_ADMIN_DATATABLE === true;
    }

    function debugLog(label, payload) {
        if (!isDebugEnabled()) {
            return;
        }
        const line = payload === undefined ? label : `${label} ${JSON.stringify(payload)}`;
        console.log(`[AdminDataTable] ${line}`);
    }

    function countDataTableInstances() {
        if (!window.jQuery?.fn?.dataTable?.tables) {
            return 0;
        }
        return window.jQuery.fn.dataTable.tables({ visible: true, api: true }).length;
    }

    function collectSnapshot(root, table, searchInput, label) {
        const settings = table.settings()[0];
        const dtNode = table.table().node();
        const dtBody = table.table().body();
        const scrollHost = root.querySelector('.admin-datatable-table-scroll');
        const tablesInScroll = scrollHost
            ? scrollHost.querySelectorAll('table').length
            : 0;
        const filterInputs = root.querySelectorAll('.dataTables_filter input');
        const tbodyInDom = dtNode?.tBodies?.[0] || null;
        const trInDtTbody = tbodyInDom ? tbodyInDom.querySelectorAll('tr').length : 0;
        const trInScroll = scrollHost ? scrollHost.querySelectorAll('tbody tr').length : 0;

        const snapshot = {
            label,
            tableId: dtNode?.id || null,
            dtInstanceCount: countDataTableInstances(),
            extSearchHandlers: window.jQuery?.fn?.dataTable?.ext?.search?.length ?? 0,
            searchInputCount: filterInputs.length,
            searchInputValue: searchInput?.value ?? null,
            searchApi: table.search(),
            bFilter: settings?.oFeatures?.bFilter ?? null,
            aiDisplayLen: settings?.aiDisplay?.length ?? null,
            aiDisplayMasterLen: settings?.aiDisplayMaster?.length ?? null,
            rowsTotalCount: table.rows().count(),
            rowsAppliedCount: table.rows({ search: 'applied' }).count(),
            rowsPageAppliedCount: table.rows({ page: 'current', search: 'applied' }).count(),
            tbodyTrCount: trInDtTbody,
            tbodyTrInScrollHost: trInScroll,
            tablesInsideScrollHost: tablesInScroll,
            tbodyOwnedByDt: tbodyInDom === dtBody,
            infoText: root.querySelector('.dataTables_info')?.textContent?.trim() || null,
        };

        window.__ADMIN_DT_LAST_SNAPSHOT__ = snapshot;
        debugLog(`snapshot:${label}`, snapshot);
        return snapshot;
    }

    const SORT_COLUMN_MAP_DEFAULT = {
        0: 'tipo_registro',
        1: 'id_cigam',
        2: 'nome_exibicao',
        3: 'documento',
        4: 'unidade_referencia',
        5: 'tipo_pessoa',
        6: 'status',
    };

    const PRINT_CSS = `
        @page { margin: 12mm; }
        body {
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        h1 {
            border-bottom: 2px solid #0d6efd;
            color: #111827;
            font-size: 18px;
            margin: 0 0 14px;
            padding-bottom: 8px;
        }
        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        thead th {
            background: #eef2ff !important;
            border: 1px solid #cbd5e1 !important;
            color: #1e293b !important;
            font-size: 10px;
            letter-spacing: .02em;
            padding: 6px 7px !important;
            text-transform: uppercase;
        }
        tbody td {
            border: 1px solid #e2e8f0 !important;
            padding: 5px 7px !important;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td {
            background: #f8fafc !important;
        }
        code {
            background: transparent;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }
    `;

    function ensureToastHost() {
        let host = document.getElementById(TOAST_HOST_ID);
        if (host) {
            return host;
        }

        host = document.createElement('div');
        host.id = TOAST_HOST_ID;
        host.className = 'admin-datatable-toast-host';
        host.setAttribute('aria-live', 'polite');
        host.setAttribute('aria-atomic', 'true');
        document.body.appendChild(host);
        return host;
    }

    function dismissToast(toastEl) {
        if (!toastEl || toastEl.dataset.dismissing === '1') {
            return;
        }
        toastEl.dataset.dismissing = '1';
        toastEl.classList.remove('is-visible');
        toastEl.classList.add('is-hiding');
        window.setTimeout(() => toastEl.remove(), 280);
    }

    function showToast(message, variant = 'success', durationMs = TOAST_DURATION_MS) {
        const host = ensureToastHost();
        host.querySelectorAll('.admin-datatable-toast').forEach((el) => dismissToast(el));

        const icons = {
            success: 'ri-checkbox-circle-line',
            danger: 'ri-error-warning-line',
            warning: 'ri-alert-line',
            info: 'ri-information-line',
        };

        const toast = document.createElement('div');
        toast.className = `admin-datatable-toast admin-datatable-toast--${variant}`;
        toast.innerHTML = `
            <div class="admin-datatable-toast__content">
                <i class="${icons[variant] || icons.info}" aria-hidden="true"></i>
                <span class="admin-datatable-toast__message"></span>
                <button type="button" class="admin-datatable-toast__close" aria-label="Fechar">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </div>
            <div class="admin-datatable-toast__progress" aria-hidden="true">
                <span style="animation-duration: ${durationMs}ms"></span>
            </div>
        `;

        toast.querySelector('.admin-datatable-toast__message').textContent = message;
        toast.querySelector('.admin-datatable-toast__close').addEventListener('click', () => dismissToast(toast));

        host.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        const timer = window.setTimeout(() => dismissToast(toast), durationMs);
        toast.addEventListener('transitionend', () => {
            if (toast.classList.contains('is-hiding')) {
                window.clearTimeout(timer);
            }
        });
    }

    async function writeClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (_) {
                /* fallback abaixo */
            }
        }

        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.focus();
        area.select();

        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (_) {
            ok = false;
        }
        area.remove();
        return ok;
    }

    function tableExportText(dt, exportOptions) {
        const data = dt.buttons.exportData(
            Object.assign({ decodeEntities: true }, exportOptions || { columns: ':not(:last-child)' }),
        );
        const lines = [];

        if (data.header && data.header.length) {
            lines.push(data.header.join('\t'));
        }
        (data.body || []).forEach((row) => {
            lines.push(row.join('\t'));
        });

        return lines.filter(Boolean).join('\n');
    }

    function buildCopyButton(exportOptions) {
        return {
            text: 'Copiar',
            className: 'btn btn-sm btn-light',
            action: async function (e, dt) {
                if (e) {
                    e.preventDefault();
                }

                if (isDebugEnabled()) {
                    const $input = window.jQuery(dt.table().container()).find('.dataTables_filter input').first();
                    collectSnapshot(
                        window.jQuery(dt.table().container()).closest('[data-admin-datatable]').get(0) || document.body,
                        dt,
                        $input.get(0),
                        'copy-click',
                    );
                }

                const text = tableExportText(dt, exportOptions);
                if (!text) {
                    showToast('Nenhum dado visível para copiar.', 'danger');
                    return;
                }

                const ok = await writeClipboard(text);
                showToast(
                    ok ? 'Tabela copiada para a área de transferência.' : 'Não foi possível copiar a tabela.',
                    ok ? 'info' : 'danger',
                );
            },
        };
    }

    function buildLanguage(config) {
        const entity = config.entityLabel || 'registros';
        const entitySingular = config.entityLabelSingular || entity.replace(/s$/, '') || 'registro';

        return {
            search: 'Pesquisar:',
            searchPlaceholder: config.searchPlaceholder || 'Pesquisar',
            lengthMenu: 'Exibir _MENU_',
            info: `Mostrando _START_ a _END_ de _TOTAL_ ${entity}`,
            infoEmpty: `Nenhum ${entitySingular} para exibir`,
            infoFiltered: '(filtrado de _MAX_ no total)',
            zeroRecords: `Nenhum ${entitySingular} encontrado`,
            emptyTable: `Nenhum ${entitySingular} cadastrado`,
            loadingRecords: 'Carregando…',
            processing: 'Processando…',
            paginate: {
                first: 'Primeiro',
                previous: 'Anterior',
                next: 'Próximo',
                last: 'Último',
            },
            aria: {
                sortAscending: ': ativar para ordenar a coluna em ordem crescente',
                sortDescending: ': ativar para ordenar a coluna em ordem decrescente',
            },
            buttons: {
                copy: 'Copiar',
                print: 'Imprimir',
                copyTitle: 'Copiar para a área de transferência',
                copySuccess: {
                    1: 'Uma linha copiada',
                    _: '%d linhas copiadas',
                },
            },
        };
    }

    function purgeAttributeFilterHandlers() {
        if (!window.jQuery?.fn?.dataTable?.ext?.search) {
            return;
        }

        window.jQuery.fn.dataTable.ext.search = window.jQuery.fn.dataTable.ext.search.filter(
            (fn) => !fn.adminDatatableAttrFilter,
        );
    }

    function hasRowAttributeFilters(root) {
        return Array.from(
            root.querySelectorAll('[data-table-filter], [data-datatable-row-filter]'),
        ).length > 0;
    }

    function chainCallback(defaultFn, overrideFn) {
        if (!defaultFn && !overrideFn) {
            return undefined;
        }
        if (!overrideFn) {
            return defaultFn;
        }
        if (!defaultFn) {
            return overrideFn;
        }

        return function chainedCallback() {
            defaultFn.apply(this, arguments);
            overrideFn.apply(this, arguments);
        };
    }

    function normalizeOrder(order) {
        if (!order || !Array.isArray(order)) {
            return [[0, 'asc']];
        }
        if (order.length === 2 && typeof order[0] === 'number' && typeof order[1] === 'string') {
            return [order];
        }
        return order;
    }

    function debounce(fn, waitMs) {
        let timer = null;
        return function debounced() {
            const ctx = this;
            const args = arguments;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => fn.apply(ctx, args), waitMs);
        };
    }

    /**
     * Redesenha o tbody após filtro (ja). Não chamar table.draw() dentro de search.dt —
     * isso reentra em za()/ka() e estoura a pilha ou deixa o DOM desatualizado.
     */
    function redrawFilteredRows(table, root) {
        if (root.__adminDtRedrawLock) {
            return;
        }
        root.__adminDtRedrawLock = true;
        try {
            table.draw(false);
        } finally {
            root.__adminDtRedrawLock = false;
        }
    }

    /**
     * Busca da toolbar: único fluxo após desligar handlers nativos keyup/input.DT.
     * O handler nativo atualizava aiDisplay (export/copiar ok) sem ja() (linhas na tela).
     */
    function applyToolbarSearch(table, root, $input, sortColumnMap, source) {
        if (!$input.length) {
            debugLog('search:skip', { reason: 'no-input', source });
            return;
        }

        const term = ($input.val() || '').toString();
        const searchBefore = table.search();
        debugLog('search:before', { source, term, searchBefore });
        collectSnapshot(root, table, $input.get(0), `before:${source}`);

        if (searchBefore !== term) {
            table.search(term);
        }
        redrawFilteredRows(table, root);

        debugLog('search:after-draw', { source, term, searchAfter: table.search() });
        collectSnapshot(root, table, $input.get(0), `after:${source}`);
        syncPdfFilters(root, table, sortColumnMap);
    }

    function buildToolbarSearchInitComplete(root, sortColumnMap) {
        return function toolbarSearchInitComplete() {
            const table = this.api();
            const $container = window.jQuery(table.table().container());
            const $input = $container.find('.dataTables_filter input').first();

            debugLog('initComplete', {
                tableId: table.table().node()?.id,
                inputFound: $input.length > 0,
                inputId: $input.attr('id') || null,
                containerId: $container.attr('id') || null,
            });

            collectSnapshot(root, table, $input.get(0), 'initComplete');

            const runSearch = function (source) {
                applyToolbarSearch(table, root, $input, sortColumnMap, source);
            };

            $input.off('keyup.DT search.DT input.DT');

            $container
                .off('input.adminDtToolbarSearch search.adminDtToolbarSearch', '.dataTables_filter input')
                .on(
                    'input.adminDtToolbarSearch search.adminDtToolbarSearch',
                    '.dataTables_filter input',
                    debounce(function () {
                        runSearch('toolbar-input');
                    }, 150),
                );

            table.off('search.dt.adminDtPdfSync draw.dt.adminDtDebug')
                .on('search.dt.adminDtPdfSync', function () {
                    debugLog('event:search.dt', { term: table.search() });
                    syncPdfFilters(root, table, sortColumnMap);
                })
                .on('draw.dt.adminDtDebug', function () {
                    debugLog('event:draw.dt', { term: table.search() });
                    collectSnapshot(root, table, $input.get(0), 'draw.dt');
                });

            const initial = (root.querySelector('[data-table-search]')?.value || '').trim();
            if (initial !== '') {
                if ($input.val() !== initial) {
                    $input.val(initial);
                }
                runSearch('initial-hidden-field');
            }
        };
    }

    function buildDefaults(config, root, sortColumnMap) {
        const printTitle = config.printTitle || config.title || document.title;

        return {
            autoWidth: false,
            searching: true,
            search: {
                search: '',
                regex: false,
                smart: false,
                caseInsensitive: true,
            },
            dom:
                "<'row admin-datatable-toolbar px-3 pt-3'<'col-md-3'l><'col-md-6 text-center'B><'col-md-3'f>>" +
                "<'row'<'col-12 admin-datatable-table-scroll'tr>>" +
                "<'row px-3 pb-3'<'col-md-5'i><'col-md-7'p>>",
            buttons: [
                buildCopyButton({ columns: ':not(:last-child)' }),
                {
                    extend: 'print',
                    text: 'Imprimir',
                    className: 'btn btn-sm btn-light',
                    title: printTitle,
                    exportOptions: { columns: ':not(:last-child)' },
                    customize: function (win) {
                        win.document.head.insertAdjacentHTML('beforeend', `<style>${PRINT_CSS}</style>`);
                    },
                },
            ],
            keyTable: config.keyTable === true,
            initComplete: buildToolbarSearchInitComplete(root, sortColumnMap),
            pageLength: config.pageLength ?? 25,
            lengthMenu: config.lengthMenu ?? [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'Todos'],
            ],
            order: normalizeOrder(config.order),
            columnDefs: config.columnDefs ?? [
                { targets: -1, orderable: false, searchable: false },
            ],
            language: buildLanguage(config),
        };
    }

    function mergeOptions(defaults, overrides) {
        const merged = { ...defaults, ...overrides };
        if (overrides.language) {
            merged.language = { ...defaults.language, ...overrides.language };
        }
        if (overrides.columnDefs) {
            merged.columnDefs = overrides.columnDefs;
        }
        if (overrides.buttons) {
            merged.buttons = overrides.buttons;
        }
        merged.initComplete = chainCallback(defaults.initComplete, overrides.initComplete);
        return merged;
    }

    function syncPdfFilters(root, table, sortColumnMap) {
        const searchInput = root.querySelector('[data-table-search]');
        const sortInput = root.querySelector('[data-table-sort]');
        const directionInput = root.querySelector('[data-table-direction]');
        const perPageInput = root.querySelector('[data-table-per-page]');

        if (searchInput) {
            searchInput.value = table.search();
        }

        const order = table.order();
        if (order.length > 0 && sortInput && directionInput) {
            const [columnIndex, direction] = order[0];
            const sortKey = sortColumnMap[columnIndex] || sortColumnMap[String(columnIndex)] || sortInput.value || 'nome_exibicao';
            sortInput.value = sortKey;
            directionInput.value = direction;
        }

        if (perPageInput) {
            perPageInput.value = 'all';
        }
    }

    function updateHorizontalScroll(root) {
        const scrollHost = root.querySelector('.admin-datatable-table-scroll');
        if (!scrollHost) {
            return;
        }

        const table = scrollHost.querySelector('table.dataTable, table.admin-datatable-table');
        if (!table) {
            scrollHost.classList.remove('is-scrollable');
            return;
        }

        const needsScroll = table.scrollWidth > scrollHost.clientWidth + 1;
        scrollHost.classList.toggle('is-scrollable', needsScroll);
    }

    function bindAttributeFilters(root, table) {
        if (!hasRowAttributeFilters(root)) {
            return;
        }

        const filterKey = 'adminDatatableAttrFilter';
        const tableNode = table.table().node();
        const tableDomId = tableNode?.id || '';
        const settingsInstance = table.settings()[0]?.sInstance;

        purgeAttributeFilterHandlers();

        const handler = function (settings, data, dataIndex) {
            const sameTable =
                (settings.sInstance && settings.sInstance === settingsInstance) ||
                (settings.nTable?.id && settings.nTable.id === tableDomId);

            if (!sameTable) {
                return true;
            }

            const rowNode = settings.aoData?.[dataIndex]?.nTr;
            if (!rowNode) {
                return false;
            }

            const filters = root.querySelectorAll('[data-datatable-row-filter], [data-table-filter]');
            for (const filterEl of filters) {
                const attr = filterEl.getAttribute('name') || filterEl.dataset.datatableRowFilter || filterEl.dataset.tableFilter;
                const value = (filterEl.value || '').toString();
                if (!attr || value === '') {
                    continue;
                }
                const dataKey = 'data-' + attr.replace(/_/g, '-');
                const rowValue = rowNode.getAttribute(dataKey) ?? '';
                if (rowValue !== value) {
                    return false;
                }
            }

            return true;
        };
        handler[filterKey] = tableDomId;

        window.jQuery.fn.dataTable.ext.search.push(handler);

        root.querySelectorAll('[data-datatable-row-filter], [data-table-filter]').forEach((el) => {
            el.addEventListener('change', () => {
                table.draw();
                syncPdfFilters(root, table, root.__adminDatatableSortMap || SORT_COLUMN_MAP_DEFAULT);
            });
        });
    }

    function initRoot(root) {
        const tableId = root.dataset.tableId;
        if (!tableId) {
            return;
        }

        const tableElement = document.getElementById(tableId);
        if (!tableElement || typeof window.jQuery === 'undefined' || !window.jQuery.fn.DataTable) {
            debugLog('init:abort', { tableId, reason: 'missing-deps-or-element' });
            return;
        }

        const alreadyDataTable = window.jQuery.fn.DataTable.isDataTable(tableElement);
        if (root.dataset.bound === '1') {
            if (alreadyDataTable) {
                debugLog('init:skip', { tableId, reason: 'already-bound-and-isDataTable' });
                return;
            }
            debugLog('init:rebind', { tableId, reason: 'bound-flag-without-datatable' });
            root.dataset.bound = '0';
        }

        if (alreadyDataTable) {
            debugLog('init:skip', { tableId, reason: 'isDataTable-without-bound-flag' });
            return;
        }

        purgeAttributeFilterHandlers();

        let config = {};
        try {
            config = JSON.parse(root.dataset.config || '{}');
        } catch (_) {
            config = {};
        }

        const sortColumnMap = config.sortColumnMap || SORT_COLUMN_MAP_DEFAULT;
        root.__adminDatatableSortMap = sortColumnMap;

        const defaults = buildDefaults(config, root, sortColumnMap);
        const overrides = config.dataTable || {};
        const options = mergeOptions(defaults, overrides);

        debugLog('init:start', {
            tableId,
            extSearchHandlers: window.jQuery.fn.dataTable.ext.search.length,
            dtInstancesBefore: countDataTableInstances(),
        });

        const table = window.jQuery(tableElement).DataTable(options);
        root.dataset.bound = '1';
        root.__adminDatatableApi = table;

        debugLog('init:done', {
            tableId,
            dtInstancesAfter: countDataTableInstances(),
            isDataTable: window.jQuery.fn.DataTable.isDataTable(tableElement),
        });

        bindAttributeFilters(root, table);

        const resync = () => syncPdfFilters(root, table, sortColumnMap);
        const refreshScroll = () => updateHorizontalScroll(root);
        table.on('search.dt order.dt draw.dt column-sizing.dt', resync);
        table.on('draw.dt column-sizing.dt', refreshScroll);
        resync();
        refreshScroll();

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(refreshScroll);
            const scrollHost = root.querySelector('.admin-datatable-table-scroll');
            if (scrollHost) {
                observer.observe(scrollHost);
            }
            observer.observe(root);
            root.__adminDatatableResizeObserver = observer;
        } else {
            window.addEventListener('resize', refreshScroll);
        }

        const hasActiveRowFilters = Array.from(
            root.querySelectorAll('[data-table-filter], [data-datatable-row-filter]'),
        ).some((el) => (el.value || '').toString() !== '');
        if (hasActiveRowFilters) {
            table.draw();
        }
    }

    function bootstrap() {
        document.querySelectorAll('[data-admin-datatable]').forEach(initRoot);
    }

    window.AdminDataTable = {
        initRoot,
        bootstrap,
        showToast,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})(window);
