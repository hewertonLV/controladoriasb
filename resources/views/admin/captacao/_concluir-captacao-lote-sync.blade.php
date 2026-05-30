@push('scripts')
<script>
(function () {
    const panelId = @json($panelId ?? 'captacao-concluir-lote-panel');
    const urlEstado = @json(route('admin.captacao.lotes.matriz.estado', $lote));
    const panel = document.getElementById(panelId);
    if (!panel) {
        return;
    }

    function escHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function aplicarPendenciasConclusao(pendencias, pode) {
        const btnPendencias = panel.querySelector('[data-role="btn-pendencias"]');
        const lista = panel.querySelector('[data-role="lista-pendencias"]');
        const modal = panel.querySelector('[data-role="modal-pendencias"]');
        const btn = panel.querySelector('[data-role="btn-submit"]');
        const form = panel.querySelector('[data-role="form-concluir"]');

        if (btnPendencias) {
            btnPendencias.classList.toggle('d-none', pendencias.length === 0);
        }

        if (lista) {
            lista.innerHTML = pendencias.map((p) => `<li>${escHtml(p)}</li>`).join('');
        }

        if (pendencias.length === 0 && modal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(modal)?.hide();
        }

        if (btn) {
            btn.disabled = !pode;
            btn.title = pode ? 'Encerrar captação deste lote' : 'Resolva as pendências listadas';
        }
        if (form) {
            form.onsubmit = pode ? null : () => false;
        }
    }

    function aplicarConclusaoCaptacaoLote(info) {
        if (!info) {
            return;
        }

        panel.dataset.loteStatus = info.lote_status || panel.dataset.loteStatus;

        const badge = panel.querySelector('[data-role="badge-concluido"]');
        const emAndamento = panel.querySelector('[data-role="em-andamento"]');

        if (info.lote_status === 'CAPTACAO_CONCLUIDA') {
            if (badge) {
                badge.classList.remove('d-none');
            }
            if (emAndamento) {
                emAndamento.classList.add('d-none');
            }
            panel.querySelector('[data-role="modal-pendencias"]')
                && typeof bootstrap !== 'undefined'
                && bootstrap.Modal.getInstance(panel.querySelector('[data-role="modal-pendencias"]'))?.hide();
            return;
        }

        if (info.lote_status !== 'CAPTACAO_EM_ANDAMENTO') {
            return;
        }

        if (badge) {
            badge.classList.add('d-none');
        }
        if (emAndamento) {
            emAndamento.classList.remove('d-none');
        }

        const pendencias = Array.isArray(info.pendencias) ? info.pendencias : [];
        aplicarPendenciasConclusao(pendencias, !!info.pode);
    }

    async function syncConclusaoCaptacaoLote() {
        try {
            const res = await fetch(urlEstado, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            if (data.conclusao_captacao_lote) {
                aplicarConclusaoCaptacaoLote(data.conclusao_captacao_lote);
            }
        } catch (e) {
            /* ignore */
        }
    }

    window.aplicarConclusaoCaptacaoLote = aplicarConclusaoCaptacaoLote;
    window.syncConclusaoCaptacaoLote = syncConclusaoCaptacaoLote;

    if (panel.dataset.loteStatus === 'CAPTACAO_EM_ANDAMENTO') {
        syncConclusaoCaptacaoLote();
        let timer = null;
        const agendar = () => {
            if (timer !== null) {
                clearTimeout(timer);
            }
            timer = window.setTimeout(async () => {
                await syncConclusaoCaptacaoLote();
                agendar();
            }, document.hidden ? 8000 : 3000);
        };
        agendar();
        document.addEventListener('visibilitychange', () => {
            syncConclusaoCaptacaoLote();
            agendar();
        });
    }
})();
</script>
@endpush
