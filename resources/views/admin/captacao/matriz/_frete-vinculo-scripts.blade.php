@once
    @push('scripts')
        <script>
        (function () {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            function rotuloStatus(idFrete) {
                return idFrete ? 'Vinculado' : 'Sem Frete';
            }

            function classeStatus(idFrete) {
                return idFrete ? 'text-success' : 'text-muted';
            }

            function atualizarRotulo(wrap, idFrete, estado) {
                const el = wrap.closest('tr')?.querySelector('.captacao-frete-status')
                    ?? wrap.closest('.d-flex')?.querySelector('.captacao-frete-status');

                if (!el) {
                    return;
                }

                if (estado === 'salvando') {
                    el.textContent = 'Salvando…';
                    el.className = 'captacao-frete-status small text-warning';

                    return;
                }

                if (estado === 'erro') {
                    el.textContent = 'Erro ao salvar';
                    el.className = 'captacao-frete-status small text-danger';

                    return;
                }

                el.textContent = rotuloStatus(idFrete);
                el.className = 'captacao-frete-status small fw-semibold ' + classeStatus(idFrete);
            }

            function atualizarBotaoRemover(wrap, idFrete) {
                const btn = wrap.querySelector('.captacao-frete-remover');
                if (!btn) {
                    return;
                }

                btn.classList.toggle('d-none', !idFrete);
                btn.disabled = false;
            }

            function limparSelectFrete(select) {
                select.value = '';

                if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                    window.jQuery(select).val(null).trigger('change.select2');
                }
            }

            async function mensagemErroResposta(res) {
                try {
                    const data = await res.json();
                    if (data?.message) {
                        return data.message;
                    }
                    if (data?.errors) {
                        return Object.values(data.errors).flat().join(' ');
                    }
                } catch (e) {
                    /* ignore */
                }

                return 'Não foi possível salvar o frete.';
            }

            async function salvarFrete(wrap, select) {
                const url = wrap.dataset.url;
                if (!url || !token) {
                    return;
                }

                const valor = select.value;
                const formData = new FormData();
                formData.append('_token', token);

                if (wrap.dataset.transferenciaOrigemId) {
                    formData.append('transferencia_origem_id', wrap.dataset.transferenciaOrigemId);
                }
                if (wrap.dataset.idCliente) {
                    formData.append('id_cliente', wrap.dataset.idCliente);
                }
                if (valor !== '') {
                    formData.append('id_frete', valor);
                } else {
                    formData.append('id_frete', '');
                }

                const btnRemover = wrap.querySelector('.captacao-frete-remover');
                select.disabled = true;
                if (btnRemover) {
                    btnRemover.disabled = true;
                }
                atualizarRotulo(wrap, valor !== '' ? Number(valor) : null, 'salvando');

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: formData,
                    });

                    if (!res.ok) {
                        select.value = wrap.dataset.freteValorSalvo ?? '';
                        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                            window.jQuery(select).val(select.value || null).trigger('change.select2');
                        }
                        atualizarRotulo(
                            wrap,
                            (wrap.dataset.freteValorSalvo ?? '') !== '' ? Number(wrap.dataset.freteValorSalvo) : null,
                            'erro',
                        );
                        atualizarBotaoRemover(
                            wrap,
                            (wrap.dataset.freteValorSalvo ?? '') !== '' ? Number(wrap.dataset.freteValorSalvo) : null,
                        );
                        console.error('[Captacao frete]', await mensagemErroResposta(res));

                        return;
                    }

                    const data = await res.json();
                    const idFrete = data.id_frete ?? null;
                    wrap.dataset.freteValorSalvo = idFrete ? String(idFrete) : '';
                    atualizarRotulo(wrap, idFrete, 'ok');
                    atualizarBotaoRemover(wrap, idFrete);
                } catch (e) {
                    select.value = wrap.dataset.freteValorSalvo ?? '';
                    if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                        window.jQuery(select).val(select.value || null).trigger('change.select2');
                    }
                    atualizarRotulo(
                        wrap,
                        (wrap.dataset.freteValorSalvo ?? '') !== '' ? Number(wrap.dataset.freteValorSalvo) : null,
                        'erro',
                    );
                    atualizarBotaoRemover(
                        wrap,
                        (wrap.dataset.freteValorSalvo ?? '') !== '' ? Number(wrap.dataset.freteValorSalvo) : null,
                    );
                } finally {
                    select.disabled = false;
                    if (btnRemover) {
                        btnRemover.disabled = false;
                    }
                }
            }

            function removerVinculo(wrap, select) {
                if ((wrap.dataset.freteValorSalvo ?? '') === '') {
                    limparSelectFrete(select);

                    return;
                }

                limparSelectFrete(select);

                if (select.dataset.freteSalvando === '1') {
                    return;
                }

                select.dataset.freteSalvando = '1';
                salvarFrete(wrap, select).finally(() => {
                    delete select.dataset.freteSalvando;
                });
            }

            function vincularSelect(select) {
                const wrap = select.closest('.captacao-frete-vinculo');
                if (!wrap || wrap.dataset.freteVinculoBound === '1') {
                    return;
                }

                wrap.dataset.freteVinculoBound = '1';

                const btnRemover = wrap.querySelector('.captacao-frete-remover');
                btnRemover?.addEventListener('click', () => {
                    removerVinculo(wrap, select);
                });

                const aoAlterar = () => {
                    const salvo = wrap.dataset.freteValorSalvo ?? '';
                    if (select.value === salvo) {
                        return;
                    }

                    if (select.dataset.freteSalvando === '1') {
                        return;
                    }

                    select.dataset.freteSalvando = '1';
                    salvarFrete(wrap, select).finally(() => {
                        delete select.dataset.freteSalvando;
                    });
                };

                select.addEventListener('change', aoAlterar);

                if (window.jQuery?.fn?.select2) {
                    window.jQuery(select).on('select2:select select2:clear', aoAlterar);
                }
            }

            function iniciar() {
                document.querySelectorAll('.captacao-frete-select').forEach(vincularSelect);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', iniciar);
            } else {
                iniciar();
            }
        })();
        </script>
    @endpush
@endonce
