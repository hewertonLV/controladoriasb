@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let demandaNfUploadUrl = null;

    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mostrarModalFaltasDemanda(linhas, subtitulo) {
        const modalEl = document.getElementById('modal-demanda-faltas-estoque');
        const corpo = document.getElementById('modal-demanda-faltas-corpo');
        const subEl = document.getElementById('modal-demanda-faltas-subtitulo');
        if (!modalEl || !corpo || typeof bootstrap === 'undefined') {
            return;
        }

        if (subEl && subtitulo) {
            subEl.textContent = subtitulo;
        }

        corpo.innerHTML = '';
        (linhas || []).filter((l) => l && l.ok === false).forEach((linha) => {
            corpo.innerHTML += `<tr>
                <td class="py-1 px-2">${escHtml(linha.fruta_nome || 'Fruta')}</td>
                <td class="text-end py-1 px-2">${escHtml(String(linha.qtd_disponivel ?? '—'))}</td>
                <td class="text-end py-1 px-2 text-danger">${escHtml(String(linha.qtd_falta ?? '—'))}</td>
            </tr>`;
        });

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function tratarErroDemandaEstoque(data) {
        const linhas = data?.errors?.linhas || data?.errors?.faltas;
        if (Array.isArray(linhas) && linhas.some((l) => l && l.ok === false)) {
            mostrarModalFaltasDemanda(linhas, 'Ajuste o estoque no galpão ou revise a demanda.');
            return;
        }
        const msgs = data?.errors?.estoque;
        if (Array.isArray(msgs) && msgs.length) {
            window.alert(msgs.join(' · '));
            return;
        }
        window.alert('Não foi possível concluir a ação da demanda.');
    }

    async function executarAcaoDemandaJson(url, method) {
        if (!url) {
            return;
        }

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                tratarErroDemandaEstoque(data);
                return;
            }

            window.location.reload();
        } catch (e) {
            window.alert('Não foi possível concluir a ação da demanda.');
        }
    }

    document.addEventListener('click', async (event) => {
        const btnIniciar = event.target.closest('.btn-demanda-iniciar-transferencia');
        const btnEfetivar = event.target.closest('.btn-demanda-efetivar-venda');
        const btnExcluir = event.target.closest('.btn-demanda-excluir-transferencia');
        const btnNf = event.target.closest('.btn-demanda-anexar-nf');

        if (btnIniciar) {
            event.preventDefault();
            await executarAcaoDemandaJson(btnIniciar.dataset.url, 'POST');
        } else if (btnEfetivar) {
            event.preventDefault();
            await executarAcaoDemandaJson(btnEfetivar.dataset.url, 'POST');
        } else if (btnExcluir) {
            event.preventDefault();
            if (window.confirm('Excluir demanda de transferência?')) {
                await executarAcaoDemandaJson(btnExcluir.dataset.url, 'DELETE');
            }
        } else if (btnNf) {
            event.preventDefault();
            demandaNfUploadUrl = btnNf.dataset.url;
            const input = document.querySelector('.captacao-demanda-nf-input');
            if (input) {
                input.value = '';
                input.click();
            }
        }
    });

    const nfInput = document.querySelector('.captacao-demanda-nf-input');
    if (nfInput) {
        nfInput.addEventListener('change', async function () {
            if (!demandaNfUploadUrl || !this.files?.length) {
                return;
            }

            const formData = new FormData();
            formData.append('arquivo_nf', this.files[0]);

            try {
                const res = await fetch(demandaNfUploadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    tratarErroDemandaEstoque(data);
                    return;
                }

                window.location.reload();
            } catch (e) {
                window.alert('Não foi possível anexar a NF.');
            } finally {
                demandaNfUploadUrl = null;
                this.value = '';
            }
        });
    }
})();
</script>
@endpush
