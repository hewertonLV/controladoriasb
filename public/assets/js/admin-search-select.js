(function () {
    'use strict';

    function hasSelect2() {
        return Boolean(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2);
    }

    function initSearchSelects(root) {
        if (!hasSelect2()) {
            return;
        }

        const $root = root ? window.jQuery(root) : null;
        const $selects = $root
            ? $root.find('[data-search-select]').add($root.filter('[data-search-select]'))
            : window.jQuery('[data-search-select]');

        $selects.each(function () {
            const $select = window.jQuery(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                allowClear: !this.required,
                language: {
                    noResults: () => 'Nenhum resultado encontrado',
                    searching: () => 'Pesquisando…',
                },
                placeholder: this.dataset.placeholder || 'Selecione…',
                width: '100%',
            });
        });
    }

    function destroySearchSelects(root) {
        if (!hasSelect2()) {
            return;
        }

        const $root = root ? window.jQuery(root) : null;
        const $selects = $root
            ? $root.find('.select2-hidden-accessible')
            : window.jQuery('.select2-hidden-accessible');

        $selects.each(function () {
            window.jQuery(this).select2('destroy');
        });
    }

    function refreshSearchSelect(selectEl) {
        if (!hasSelect2() || !selectEl) {
            return;
        }

        const $select = window.jQuery(selectEl);

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }

    window.AdminSearchSelect = {
        init: initSearchSelects,
        destroy: destroySearchSelects,
        refresh: refreshSearchSelect,
    };

    document.addEventListener('DOMContentLoaded', function () {
        initSearchSelects();
    });
})();
