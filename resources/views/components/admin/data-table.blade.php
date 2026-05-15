@props([
    'title',
    'subtitle' => null,
    'searchPlaceholder' => 'Pesquisar...',
    'endpoint',
    'currentSearch' => '',
    'currentPerPage' => 20,
    'currentSort' => null,
    'currentDirection' => null,
    'perPageOptions' => [10, 20, 50, 100],
    'showSearch' => true,
    'showPerPage' => true,
    'containerId' => null,
])

@php
    $containerId = $containerId ?? 'admin-data-table-' . \Illuminate\Support\Str::random(8);
    $rootId = $containerId . '-root';
    $hasFilters = isset($filters);
@endphp

<div class="card admin-data-table"
     id="{{ $rootId }}"
     data-admin-data-table
     data-endpoint="{{ $endpoint }}"
     data-container="{{ $containerId }}">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <h4 class="header-title mb-0">{{ $title }}</h4>
            @if ($subtitle)
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>

    @if ($showSearch || $hasFilters || $showPerPage)
        <div class="card-body border-bottom">
            <div class="row g-2 align-items-end">
                @if ($showSearch)
                    <div class="col-md-{{ $hasFilters ? 5 : 9 }}">
                        <label class="form-label small text-muted mb-1" for="{{ $containerId }}-search">Pesquisa</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="search"
                                   id="{{ $containerId }}-search"
                                   value="{{ $currentSearch }}"
                                   class="form-control"
                                   placeholder="{{ $searchPlaceholder }}"
                                   data-table-search
                                   autocomplete="off">
                            <span class="input-group-text bg-white d-none" data-table-loading>
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                            </span>
                        </div>
                    </div>
                @endif

                @if ($hasFilters)
                    {{ $filters }}
                @endif

                @if ($showPerPage)
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1" for="{{ $containerId }}-perpage">Por página</label>
                        <select id="{{ $containerId }}-perpage" class="form-select" data-table-per-page>
                            @foreach ($perPageOptions as $opt)
                                <option value="{{ $opt }}" @selected((string) $currentPerPage === (string) $opt)>{{ $opt }}</option>
                            @endforeach
                            <option value="all" @selected((string) $currentPerPage === 'all')>Todos</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <input type="hidden" value="{{ $currentSort }}" data-table-sort>
    <input type="hidden" value="{{ $currentDirection }}" data-table-direction>

    <div id="{{ $containerId }}" data-table-container>
        {{ $slot }}
    </div>
</div>

@once
    @push('head')
    <style>
        /*
         * Paginação Bootstrap 5 (`Paginator::useBootstrapFive()`) compactada
         * para o tema Highdmin. Aplicada apenas dentro do card-footer da
         * data-table e do wrapper `.admin-table-pagination`.
         */
        .admin-data-table .card-footer .pagination,
        .admin-table-pagination .pagination {
            --bs-pagination-padding-x: 0.65rem;
            --bs-pagination-padding-y: 0.3rem;
            --bs-pagination-font-size: 0.8125rem;
            --bs-pagination-border-radius: 0.25rem;
            margin-bottom: 0;
            flex-wrap: wrap;
            gap: 2px;
        }
        .admin-data-table .card-footer .pagination .page-link,
        .admin-table-pagination .pagination .page-link {
            min-width: 32px;
            line-height: 1.2;
            text-align: center;
        }
        .admin-data-table .card-footer .pagination svg,
        .admin-table-pagination .pagination svg {
            /* Defesa contra SVGs gigantes herdados de outros templates. */
            width: 14px;
            height: 14px;
        }
        .admin-data-table .sortable-th {
            white-space: nowrap;
        }
        .admin-data-table .sortable-th a {
            align-items: center;
            color: inherit;
            cursor: pointer;
            display: inline-flex;
            gap: 0.35rem;
            text-decoration: none;
            transition: color 0.15s ease, opacity 0.15s ease;
        }
        .admin-data-table .sortable-th a:hover {
            color: var(--bs-primary);
        }
        .admin-data-table .sortable-th.is-active a {
            color: var(--bs-primary);
            font-weight: 700;
        }
        .admin-data-table .sort-icon {
            font-size: 0.9rem;
            line-height: 1;
            opacity: 0.55;
        }
        .admin-data-table .sortable-th.is-active .sort-icon {
            opacity: 0.9;
        }
        .admin-data-table.is-loading [data-table-container] {
            opacity: 0.72;
            transition: opacity 0.15s ease;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        function debounce(fn, wait) {
            let t;
            return function (...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        function buildParams(root) {
            const params = new URLSearchParams();
            const search = root.querySelector('[data-table-search]');
            const perPage = root.querySelector('[data-table-per-page]');
            const sort = root.querySelector('[data-table-sort]');
            const direction = root.querySelector('[data-table-direction]');
            const filters = root.querySelectorAll('[data-table-filter]');

            if (search && search.value.trim() !== '') {
                params.set('search', search.value.trim());
            }
            if (perPage && perPage.value !== '') {
                params.set('per_page', perPage.value);
            }
            if (sort && sort.value !== '') {
                params.set('sort', sort.value);
            }
            if (direction && direction.value !== '') {
                params.set('direction', direction.value);
            }
            filters.forEach((el) => {
                const name = el.getAttribute('name') || el.dataset.tableFilter;
                if (!name) return;
                const value = (el.value || '').toString();
                if (value !== '') params.set(name, value);
            });
            return params;
        }

        function syncStateFromUrl(root, url) {
            const search = root.querySelector('[data-table-search]');
            const perPage = root.querySelector('[data-table-per-page]');
            const sort = root.querySelector('[data-table-sort]');
            const direction = root.querySelector('[data-table-direction]');
            const filters = root.querySelectorAll('[data-table-filter]');

            if (search) search.value = url.searchParams.get('search') || '';
            if (perPage) perPage.value = url.searchParams.get('per_page') || perPage.value;
            if (sort) sort.value = url.searchParams.get('sort') || '';
            if (direction) direction.value = url.searchParams.get('direction') || '';

            filters.forEach((el) => {
                const name = el.getAttribute('name') || el.dataset.tableFilter;
                if (!name) return;
                el.value = url.searchParams.get(name) || '';
            });
        }

        function updateExportLinks(root, params) {
            const exportLinks = root.querySelectorAll('[data-export-link]');
            exportLinks.forEach((link) => {
                const base = link.getAttribute('data-export-base') || link.getAttribute('href').split('?')[0];
                link.setAttribute('data-export-base', base);
                const qs = params.toString();
                link.setAttribute('href', qs ? `${base}?${qs}` : base);
            });
        }

        async function reload(root, urlOverride = null) {
            const endpoint = root.getAttribute('data-endpoint');
            if (!endpoint) return;

            const formParams = buildParams(root);
            let url;
            if (urlOverride) {
                url = new URL(urlOverride, window.location.origin);
            } else {
                url = new URL(endpoint, window.location.origin);
                formParams.forEach((v, k) => url.searchParams.set(k, v));
            }

            const containerId = root.getAttribute('data-container');
            const container = document.getElementById(containerId);
            const loading = root.querySelector('[data-table-loading]');
            if (loading) loading.classList.remove('d-none');
            root.classList.add('is-loading');

            try {
                const resp = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                });
                if (!resp.ok) {
                    container.innerHTML = '<div class="alert alert-danger m-3">Falha ao carregar dados (HTTP ' + resp.status + ').</div>';
                    return;
                }
                const html = await resp.text();
                container.innerHTML = html;
                syncStateFromUrl(root, url);
                updateExportLinks(root, buildParams(root));
                history.replaceState(null, '', url.pathname + url.search);
            } catch (err) {
                container.innerHTML = '<div class="alert alert-danger m-3">Falha de comunicação: ' + (err.message || err) + '</div>';
            } finally {
                if (loading) loading.classList.add('d-none');
                root.classList.remove('is-loading');
            }
        }

        function init(root) {
            if (root.dataset.bound === '1') return;
            root.dataset.bound = '1';

            const searchEl = root.querySelector('[data-table-search]');
            const perPageEl = root.querySelector('[data-table-per-page]');
            const filterEls = root.querySelectorAll('[data-table-filter]');

            if (searchEl) {
                searchEl.addEventListener('input', debounce(() => reload(root), 300));
                searchEl.addEventListener('search', () => reload(root));
            }
            if (perPageEl) {
                perPageEl.addEventListener('change', () => reload(root));
            }
            filterEls.forEach((el) => el.addEventListener('change', () => reload(root)));

            const containerId = root.getAttribute('data-container');
            const container = document.getElementById(containerId);
            if (container) {
                container.addEventListener('click', (e) => {
                    const link = e.target.closest('a.page-link, [data-table-link]');
                    if (!link) return;
                    const href = link.getAttribute('href');
                    if (!href || href === '#' || link.getAttribute('aria-disabled') === 'true') return;
                    e.preventDefault();
                    reload(root, href);
                });
            }

            updateExportLinks(root, buildParams(root));
        }

        function bootstrap() {
            document.querySelectorAll('[data-admin-data-table]').forEach(init);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootstrap);
        } else {
            bootstrap();
        }
    })();
    </script>
    @endpush
@endonce
