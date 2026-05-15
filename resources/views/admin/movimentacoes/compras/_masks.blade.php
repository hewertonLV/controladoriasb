<script>
(function () {
    function onlyDigitsAndSeparators(v) {
        return String(v).replace(/[^\d.,]/g, '');
    }

    function bindDecimalBr(sel) {
        var el = document.querySelector(sel);
        if (!el) return;
        el.addEventListener('blur', function () {
            var raw = onlyDigitsAndSeparators(el.value).replace(/\./g, '').replace(',', '.');
            if (raw === '' || raw === '.') return;
            var n = parseFloat(raw);
            if (isNaN(n)) return;
            el.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
    }

    function bindMoneyBr(sel) {
        var el = document.querySelector(sel);
        if (!el) return;
        el.addEventListener('blur', function () {
            var t = String(el.value).trim();
            if (t === '') return;
            t = t.replace(/^R\$\s?/i, '');
            t = onlyDigitsAndSeparators(t).replace(/\./g, '').replace(',', '.');
            if (t === '' || t === '.') return;
            var n = parseFloat(t);
            if (isNaN(n)) return;
            el.value = 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindDecimalBr('[data-mask-decimal-br]');
        bindMoneyBr('[data-mask-money-br]');
    });
})();
</script>
