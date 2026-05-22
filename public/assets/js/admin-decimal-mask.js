/**
 * Máscara decimal pt-BR.
 *
 * - [data-mask-decimal-br] — digitação livre com vírgula; milhar com ponto.
 * - [data-mask-decimal-br-cents] — só dígitos; formata como centavos (1 → 0,01; 1234 → 12,34).
 * - [data-mask-price-br] — só dígitos; monta o valor da esquerda (5 → 5,00; 50 → 50,00; 1234 → 1.234,00).
 * - [data-mask-integer-br] — só dígitos inteiros; milhar com ponto (1234 → 1.234).
 */
(function (global) {
    'use strict';

    function onlyDigits(v) {
        return String(v ?? '').replace(/\D/g, '');
    }

    function onlyDigitsCommaDot(v) {
        return String(v ?? '').replace(/[^\d.,]/g, '');
    }

    function parseBrDecimal(str) {
        var raw = onlyDigitsCommaDot(str).trim();
        if (raw === '' || raw === '.' || raw === ',') {
            return null;
        }

        var hasComma = raw.indexOf(',') !== -1;
        var hasDot = raw.indexOf('.') !== -1;

        if (hasComma && hasDot) {
            if (raw.lastIndexOf(',') > raw.lastIndexOf('.')) {
                raw = raw.replace(/\./g, '').replace(',', '.');
            } else {
                raw = raw.replace(/,/g, '');
            }
        } else if (hasComma) {
            raw = raw.replace(',', '.');
        } else if (hasDot) {
            var parts = raw.split('.');
            if (parts.length > 2) {
                var frac = parts.pop();
                raw = parts.join('') + (frac !== '' ? '.' + frac : '');
            } else if (parts.length === 2 && parts[1].length === 3 && parts[0].length <= 3) {
                raw = parts[0] + parts[1];
            }
        }

        var n = parseFloat(raw);
        if (isNaN(n)) {
            return null;
        }

        return Math.max(0, n);
    }

    function formatBrDecimal(n, minFrac, maxFrac) {
        return n.toLocaleString('pt-BR', {
            minimumFractionDigits: minFrac,
            maximumFractionDigits: maxFrac,
        });
    }

    function maskValueInput(value) {
        var clean = onlyDigitsCommaDot(value);
        if (clean === '') {
            return '';
        }

        var commaIdx = clean.lastIndexOf(',');
        var intRaw;
        var decRaw;

        if (commaIdx !== -1) {
            intRaw = clean.slice(0, commaIdx).replace(/\./g, '').replace(/\D/g, '');
            decRaw = clean.slice(commaIdx + 1).replace(/\D/g, '').slice(0, 2);
        } else {
            intRaw = clean.replace(/\./g, '').replace(/\D/g, '');
            decRaw = '';
        }

        if (intRaw === '') {
            intRaw = '0';
        }

        var intNum = parseInt(intRaw, 10);
        if (isNaN(intNum)) {
            intNum = 0;
        }

        var out = intNum.toLocaleString('pt-BR');
        if (commaIdx !== -1 || decRaw.length > 0) {
            out += ',' + decRaw;
        }

        return out;
    }

    function maskCentsInput(value) {
        var digits = onlyDigits(value);
        if (digits === '') {
            return '';
        }

        var cents = parseInt(digits, 10);
        if (isNaN(cents)) {
            return '';
        }

        return formatBrDecimal(cents / 100, 2, 2);
    }

    function maskIntegerInput(value) {
        var digits = onlyDigits(value);
        if (digits === '') {
            return '';
        }

        var n = parseInt(digits, 10);
        if (isNaN(n)) {
            return '';
        }

        return n.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function bindIntegerBr(selector, root) {
        var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        var nodes = scope.querySelectorAll(selector);

        nodes.forEach(function (el) {
            if (el.dataset.integerBrBound === '1') {
                return;
            }
            el.dataset.integerBrBound = '1';
            el.setAttribute('inputmode', 'numeric');
            el.setAttribute('autocomplete', 'off');

            var initialRaw = String(el.value ?? '').trim();
            if (initialRaw !== '') {
                el.value = maskIntegerInput(initialRaw);
            }

            el.addEventListener('keydown', function (e) {
                if (e.key === ',' || e.key === '.' || e.key === '-') {
                    e.preventDefault();
                }
            });

            el.addEventListener('input', function () {
                var masked = maskIntegerInput(el.value);
                if (el.value !== masked) {
                    el.value = masked;
                }
            });

            el.addEventListener('paste', function (e) {
                e.preventDefault();
                el.value = maskIntegerInput((e.clipboardData || window.clipboardData).getData('text'));
            });
        });
    }

    function bindPriceBr(selector, root) {
        var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        var nodes = scope.querySelectorAll(selector);

        nodes.forEach(function (el) {
            if (el.dataset.priceBrBound === '1') {
                return;
            }
            el.dataset.priceBrBound = '1';
            el.setAttribute('inputmode', 'numeric');
            el.setAttribute('autocomplete', 'off');

            function syncFromDigits(digits) {
                el.dataset.priceDigits = digits;
                el.dataset.priceUseComma = '0';

                if (digits === '') {
                    el.value = '';
                    return;
                }

                var n = parseInt(digits, 10);
                if (isNaN(n)) {
                    el.value = '';
                    return;
                }

                el.value = formatBrDecimal(n, 2, 2);
            }

            function enableCommaMode() {
                el.dataset.priceUseComma = '1';
            }

            var initialRaw = String(el.value ?? '').trim();
            if (initialRaw !== '') {
                if (initialRaw.indexOf(',') !== -1 || initialRaw.indexOf('.') !== -1) {
                    enableCommaMode();
                    el.value = maskValueInput(initialRaw);
                    var parsed = parseBrDecimal(initialRaw);
                    if (parsed !== null) {
                        el.value = formatBrDecimal(parsed, 2, 2);
                    }
                } else {
                    syncFromDigits(onlyDigits(initialRaw));
                }
            }

            el.addEventListener('keydown', function (e) {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }

                if (el.dataset.priceUseComma === '1') {
                    return;
                }

                if (e.key >= '0' && e.key <= '9') {
                    e.preventDefault();
                    syncFromDigits((el.dataset.priceDigits || '') + e.key);
                    return;
                }

                if (e.key === 'Backspace') {
                    e.preventDefault();
                    syncFromDigits((el.dataset.priceDigits || '').slice(0, -1));
                    return;
                }

                if (e.key === 'Delete') {
                    e.preventDefault();
                    syncFromDigits('');
                    return;
                }

                if (e.key === ',' || e.key === '.') {
                    e.preventDefault();
                    enableCommaMode();
                    if (el.value.indexOf(',') === -1) {
                        el.value = el.value === '' ? '0,' : el.value.replace(/,00$/, '') + ',';
                    }
                }
            });

            el.addEventListener('input', function () {
                if (el.dataset.priceUseComma === '1') {
                    el.value = maskValueInput(el.value);
                    return;
                }

                var digits = onlyDigits(el.value);
                if (digits !== (el.dataset.priceDigits || '')) {
                    syncFromDigits(digits);
                }
            });

            el.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text');
                if (String(text).indexOf(',') !== -1 || String(text).indexOf('.') !== -1) {
                    enableCommaMode();
                    el.value = maskValueInput(text);
                    return;
                }
                syncFromDigits(onlyDigits(text));
            });

            el.addEventListener('blur', function () {
                if (String(el.value).trim() === '') {
                    el.dataset.priceDigits = '';
                    el.dataset.priceUseComma = '0';
                    return;
                }

                var n = parseBrDecimal(el.value);
                if (n === null) {
                    return;
                }

                el.value = formatBrDecimal(n, 2, 2);

                if (el.dataset.priceUseComma === '1') {
                    el.dataset.priceDigits = '';
                    return;
                }

                syncFromDigits(String(Math.floor(n)));
            });
        });
    }

    function bindDecimalBr(selector, mode, root) {
        var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        var nodes = scope.querySelectorAll(selector);
        var isCents = mode === 'cents';

        nodes.forEach(function (el) {
            if (el.dataset.decimalBrBound === '1') {
                return;
            }
            el.dataset.decimalBrBound = '1';
            el.setAttribute('inputmode', 'numeric');
            el.setAttribute('autocomplete', 'off');

            var initialRaw = String(el.value ?? '').trim();
            if (initialRaw !== '') {
                if (isCents) {
                    var fromDisplay = parseBrDecimal(initialRaw);
                    if (fromDisplay !== null && fromDisplay > 0) {
                        el.value = formatBrDecimal(fromDisplay, 2, 2);
                    } else {
                        el.value = '';
                    }
                } else {
                    var initial = parseBrDecimal(initialRaw);
                    if (initial !== null) {
                        el.value = formatBrDecimal(initial, 2, 2);
                    }
                }
            }

            el.addEventListener('input', function () {
                var masked = isCents ? maskCentsInput(el.value) : maskValueInput(el.value);
                if (el.value !== masked) {
                    el.value = masked;
                }
            });

            el.addEventListener('blur', function () {
                if (String(el.value).trim() === '') {
                    return;
                }
                if (isCents) {
                    el.value = maskCentsInput(el.value);
                    return;
                }
                var n = parseBrDecimal(el.value);
                if (n === null) {
                    return;
                }
                el.value = formatBrDecimal(n, 2, 2);
            });
        });
    }

    function init(root) {
        bindDecimalBr('[data-mask-decimal-br-cents]', 'cents', root);
        bindDecimalBr('[data-mask-money-br]', 'cents', root);
        bindDecimalBr('[data-mask-decimal-br]:not([data-mask-decimal-br-cents])', 'free', root);
        bindPriceBr('[data-mask-price-br]', root);
        bindIntegerBr('[data-mask-integer-br]', root);
    }

    global.AdminDecimalMask = {
        bind: bindDecimalBr,
        bindIntegerIn: function (root) {
            bindIntegerBr('[data-mask-integer-br]', root);
        },
        bindPriceIn: function (root) {
            bindPriceBr('[data-mask-price-br]', root);
        },
        bindCentsIn: function (root) {
            bindDecimalBr('[data-mask-decimal-br-cents]', 'cents', root);
            bindDecimalBr('[data-mask-money-br]', 'cents', root);
        },
        parse: parseBrDecimal,
        format: formatBrDecimal,
        maskCents: maskCentsInput,
        init: init,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : this);
