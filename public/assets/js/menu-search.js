(function () {
    'use strict';

    const MIN_QUERY_LENGTH = 1;
    const MAX_RESULTS = 20;

    function normalizeText(value) {
        return (value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function collectMenuItems() {
        const sideNav = document.querySelector('.side-nav');

        if (!sideNav) {
            return [];
        }

        const items = [];
        let currentTitle = '';

        sideNav.querySelectorAll(':scope > li').forEach((node) => {
            if (node.classList.contains('side-nav-title')) {
                currentTitle = node.textContent.trim();
                return;
            }

            if (!node.classList.contains('side-nav-item')) {
                return;
            }

            const groupToggle = node.querySelector(':scope > a[data-bs-toggle="collapse"]');
            const groupLabel = groupToggle?.querySelector('.menu-text')?.textContent.trim() || currentTitle;

            node.querySelectorAll('a.side-nav-link[href]').forEach((link) => {
                if (link === groupToggle) {
                    return;
                }

                const href = link.getAttribute('href') || '';

                if (!href || href === '#' || href.startsWith('#')) {
                    return;
                }

                const label = link.querySelector('.menu-text')?.textContent.trim()
                    || link.textContent.trim();

                if (!label) {
                    return;
                }

                const section = groupToggle ? groupLabel : currentTitle;
                const keywords = normalizeText([currentTitle, section, label].filter(Boolean).join(' '));

                items.push({
                    label,
                    href,
                    section: section || 'Menu',
                    keywords,
                });
            });
        });

        return items;
    }

    function initMenuSearch() {
        const modal = document.getElementById('searchModal');
        const input = document.getElementById('search-modal-input');
        const resultsWrap = document.getElementById('search-modal-results-wrap');
        const resultsList = document.getElementById('search-modal-results');
        const emptyState = document.getElementById('search-modal-empty');
        const hintState = document.getElementById('search-modal-hint');

        if (!modal || !input || !resultsWrap || !resultsList) {
            return;
        }

        const menuItems = collectMenuItems();
        let activeIndex = -1;

        function setResultsVisibility(show) {
            resultsWrap.classList.toggle('d-none', !show);
            input.setAttribute('aria-expanded', show ? 'true' : 'false');
        }

        function renderResults(matches) {
            resultsList.innerHTML = '';
            activeIndex = -1;

            if (matches.length === 0) {
                emptyState?.classList.remove('d-none');
                hintState?.classList.add('d-none');
                setResultsVisibility(true);
                return;
            }

            emptyState?.classList.add('d-none');
            hintState?.classList.add('d-none');
            setResultsVisibility(true);

            matches.forEach((item, index) => {
                const button = document.createElement('a');
                button.href = item.href;
                button.className = 'list-group-item list-group-item-action py-2 px-3';
                button.setAttribute('role', 'option');
                button.dataset.index = String(index);
                button.innerHTML = `
                    <div class="fw-semibold">${item.label}</div>
                    <small class="text-muted">${item.section}</small>
                `;

                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.location.assign(item.href);
                });

                resultsList.appendChild(button);
            });
        }

        function filterMenu(query) {
            const normalizedQuery = normalizeText(query);

            if (normalizedQuery.length < MIN_QUERY_LENGTH) {
                resultsList.innerHTML = '';
                emptyState?.classList.add('d-none');
                hintState?.classList.remove('d-none');
                setResultsVisibility(false);
                return;
            }

            const matches = menuItems
                .filter((item) => item.keywords.includes(normalizedQuery))
                .slice(0, MAX_RESULTS);

            renderResults(matches);
        }

        function highlightActive() {
            const options = resultsList.querySelectorAll('[role="option"]');

            options.forEach((option, index) => {
                option.classList.toggle('active', index === activeIndex);
            });

            const active = options[activeIndex];

            if (active) {
                active.scrollIntoView({ block: 'nearest' });
            }
        }

        function navigateToActive() {
            const active = resultsList.querySelector('[role="option"].active');

            if (active instanceof HTMLAnchorElement) {
                window.location.assign(active.href);
            }
        }

        input.addEventListener('input', () => {
            filterMenu(input.value);
        });

        input.addEventListener('keydown', (event) => {
            const options = resultsList.querySelectorAll('[role="option"]');

            if (event.key === 'ArrowDown') {
                event.preventDefault();

                if (options.length === 0) {
                    return;
                }

                activeIndex = Math.min(activeIndex + 1, options.length - 1);
                highlightActive();
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();

                if (options.length === 0) {
                    return;
                }

                activeIndex = Math.max(activeIndex - 1, 0);
                highlightActive();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                navigateToActive();
            }
        });

        modal.addEventListener('shown.bs.modal', () => {
            input.value = '';
            activeIndex = -1;
            filterMenu('');
            window.setTimeout(() => input.focus(), 50);
        });

        modal.addEventListener('hidden.bs.modal', () => {
            input.value = '';
            activeIndex = -1;
            filterMenu('');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenuSearch);
    } else {
        initMenuSearch();
    }
})();
