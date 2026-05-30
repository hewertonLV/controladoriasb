@extends('layouts.app')

@section('title', ($cliente->fantasia ?: $cliente->razao_social).' — pedido')
@section('page-title', $cliente->fantasia ?: $cliente->razao_social)

@push('head')
    <style>
        .captacao-pedido-loja-compact .card { margin-bottom: 0.5rem; }
        .captacao-pedido-loja-compact .card-header { padding: 0.3rem 0.6rem; font-size: 0.875rem; }
        .captacao-pedido-loja-compact .card-footer { padding: 0.35rem 0.6rem; }
        .captacao-pedido-loja-compact .table { font-size: 0.8rem; margin-bottom: 0; }
        .captacao-pedido-loja-compact .table > :not(caption) > * > * { padding: 0.15rem 0.3rem; vertical-align: middle; }
        .captacao-pedido-loja-compact .table thead th { font-weight: 600; white-space: nowrap; }
        .captacao-pedido-loja-compact .form-control-sm {
            padding: 0.1rem 0.25rem;
            font-size: 0.8rem;
            min-height: 1.45rem;
        }
        .captacao-pedido-loja-compact .rent-seta { font-size: 0.95rem; line-height: 1; vertical-align: -1px; }
        .captacao-pedido-loja-compact .captacao-pedido-loja-input.is-valid {
            border-color: var(--bs-success, #198754);
            box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.2);
            background-image: none;
            padding-right: 0.25rem;
        }
        .captacao-pedido-loja-compact .captacao-pedido-loja-input.is-invalid {
            border-color: var(--bs-danger, #dc3545);
        }
        .captacao-pedido-loja-compact .captacao-pedido-loja-numero {
            width: 9rem;
            min-width: 7rem;
        }
        .captacao-pedido-loja-compact .captacao-pedido-loja-numero.is-valid {
            border-color: var(--bs-success, #198754);
            box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.2);
            background-image: none;
        }
        .captacao-pedido-loja-compact .captacao-pedido-loja-numero.is-invalid {
            border-color: var(--bs-danger, #dc3545);
        }
        #pedido-loja-sync-badge.sincronizando { background-color: var(--bs-info); }
        #pedido-loja-sync-badge.sincronizado { background-color: var(--bs-success); }
        #pedido-loja-sync-badge.erro { background-color: var(--bs-danger); }
        #pedido-loja-sync-badge.pendente { background-color: var(--bs-warning); color: #212529; }
        .captacao-saida-fisica-inline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.1rem 0.25rem;
            font-size: 0.8rem;
            line-height: 1.3;
            margin-bottom: 0.35rem;
        }
        .captacao-saida-fisica-inline.salvando { opacity: 0.85; }
        .captacao-saida-fisica-inline .captacao-saida-sep {
            color: var(--bs-secondary-color, #6c757d);
            padding: 0 0.15rem;
            user-select: none;
        }
        .captacao-saida-fisica-inline .form-check {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            margin: 0;
            padding: 0;
            min-height: 0;
        }
        .captacao-saida-fisica-inline .form-check-input {
            width: 0.85rem;
            height: 0.85rem;
            margin: 0;
            flex-shrink: 0;
        }
        .captacao-saida-fisica-inline .form-check-label {
            white-space: nowrap;
            font-size: inherit;
            cursor: pointer;
        }
        .captacao-pedido-loja-saida-check:checked { accent-color: var(--bs-primary); }
        .captacao-pedido-loja-compact .captacao-custo-num { font-variant-numeric: tabular-nums; }
        .captacao-pedido-loja-compact th.captacao-custo-sub,
        .captacao-pedido-loja-compact td.captacao-custo-sub { font-size: 0.72rem; max-width: 4.25rem; }
    </style>
@endpush

@section('content')
    <div class="page-container captacao-pedido-loja-compact">
        @if ($pedidoAnterior && $linhasUltimoPedido->isNotEmpty())
            <div class="card border-secondary">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-1">
                    <span>
                        <strong>Último pedido</strong>
                        <span class="text-muted">{{ $pedidoAnterior->lote?->data_referencia?->format('d/m/Y') }}</span>
                    </span>
                    @if ($rentabilidadeUltimoPedido['margem_percentual'] !== null)
                        @php
                            $rentPct = (float) $rentabilidadeUltimoPedido['margem_percentual'];
                            $rentBadge = $rentPct >= 0 ? 'bg-success' : 'bg-danger';
                            $rentSeta = $rentPct >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
                        @endphp
                        <span class="badge {{ $rentBadge }}">
                            <i class="{{ $rentSeta }} rent-seta me-1"></i>
                            Rent. {{ number_format($rentPct, 2, ',', '.') }}%
                        </span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Fruta</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Preço</th>
                            <th class="text-end">Rent.%</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($linhasUltimoPedido as $linha)
                            @php
                                $item = $linha['item'];
                                $rent = $linha['rentabilidade'];
                                $pctItem = $rent['margem_percentual'] !== null ? (float) $rent['margem_percentual'] : null;
                            @endphp
                            <tr>
                                <td class="text-truncate" style="max-width:12rem" title="{{ $item->fruta?->nome }}">
                                    {{ $item->fruta?->nome }}
                                    <span class="text-muted">({{ $item->fruta?->unidade_medicao }})</span>
                                </td>
                                <td class="text-end text-nowrap">{{ rtrim(rtrim($item->quantidade, '0'), '.') }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($item->preco_venda !== null)
                                        {{ number_format((float) $item->preco_venda, 2, ',', '.') }}
                                    @else — @endif
                                </td>
                                <td class="text-end text-nowrap {{ $pctItem !== null && $pctItem < 0 ? 'text-danger' : ($pctItem !== null ? 'text-success' : '') }}">
                                    @if ($rent['margem_percentual'] !== null)
                                        <i class="ri-{{ $pctItem >= 0 ? 'arrow-up' : 'arrow-down' }}-line rent-seta me-1"></i>{{ number_format($pctItem, 2, ',', '.') }}%
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        @if ($rentabilidadeUltimoPedido['faturamento'] !== '0.00')
                        <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Totais</th>
                            <th class="text-end">
                                @if ($rentabilidadeUltimoPedido['margem_percentual'] !== null)
                                    @php $rentTotalPct = (float) $rentabilidadeUltimoPedido['margem_percentual']; @endphp
                                    <span class="{{ $rentTotalPct >= 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="ri-{{ $rentTotalPct >= 0 ? 'arrow-up' : 'arrow-down' }}-line rent-seta me-1"></i>{{ number_format($rentTotalPct, 2, ',', '.') }}%
                                    </span>
                                @else — @endif
                            </th>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-muted text-end py-0 small">
                                Fat. {{ number_format((float) $rentabilidadeUltimoPedido['faturamento'], 2, ',', '.') }}
                                @if ((float) $cliente->desconto_nf > 0)
                                    (líquido após desc. NF {{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}%)
                                @endif
                            </td>
                        </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @elseif ($possuiFrutas)
            <div class="alert alert-secondary py-1 px-2 mb-2 small">
                Sem pedido anterior nesta carteira.
            </div>
        @endif

        @if ($possuiFrutas)
        <form id="form-pedido-loja" method="post" action="{{ route('admin.captacao.pedidos-por-loja.salvar', [$lote, $cliente]) }}">
            @csrf
            @method('PUT')
        </form>

        @php
            $titleSaidaFisica = $saidaPadraoCadastroLabel
                ? 'Padrão cadastro: '.$saidaPadraoCadastroLabel.($pedidoSaidaOverride ? ' · alterado neste lote' : '')
                : null;
        @endphp
        <div class="captacao-saida-fisica-inline"
             id="card-saida-fisica-loja"
             data-saida-fisica-loja-opcoes
             @if ($titleSaidaFisica) title="{{ $titleSaidaFisica }}" @endif>
            <span class="text-nowrap fw-semibold me-1">Saída do estoque físico:</span>
            @foreach ($opcoesSaidaFisica as $i => $opcao)
                @if ($i > 0)<span class="captacao-saida-sep" aria-hidden="true">|</span>@endif
                <div class="form-check">
                    <input type="checkbox"
                           class="form-check-input captacao-pedido-loja-saida-check"
                           id="saida-fisica-{{ $opcao['id'] }}"
                           value="{{ $opcao['id'] }}"
                           title="{{ $opcao['label'] }}"
                           data-url="{{ route('admin.captacao.pedidos-por-loja.saida-fisica-venda', [$lote, $cliente]) }}"
                           @checked((int) $idSaidaSelecionada === (int) $opcao['id'])
                           @disabled(! $podeEditar)>
                    <label class="form-check-label" for="saida-fisica-{{ $opcao['id'] }}" title="{{ $opcao['label'] }}">
                        {{ $opcao['label_curto'] }}
                    </label>
                </div>
            @endforeach
            <span class="captacao-pedido-loja-saida-status text-muted" aria-live="polite"></span>
        </div>
        @endif

        @if ($possuiFrutas && ($captacaoLoteAberta ?? false) && ($pedidoConcluido ?? false))
            <div class="alert alert-info py-1 px-2 mb-2 small">
                Pedido finalizado. Clique em <strong>Reabrir pedido</strong> para alterar quantidades, preços e número do pedido.
            </div>
        @endif

        <div class="mb-2 d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.captacao.pedidos-por-loja.lojas', $lote) }}" class="btn btn-sm btn-light py-0">
                <i class="ri-arrow-left-line"></i> Lojas
            </a>
            @if ($captacaoLoteAberta ?? false)
                <span id="pedido-loja-sync-badge" class="badge bg-secondary d-none">Aguardando</span>
                <form method="post"
                      id="form-pedido-loja-conclusao"
                      action="{{ route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $cliente]) }}"
                      class="d-inline">
                    @csrf
                    <input type="hidden" name="captacao_concluida" value="{{ $pedidoAtual?->captacao_concluida ? '0' : '1' }}">
                    <button type="submit" class="btn btn-sm py-0 {{ $pedidoAtual?->captacao_concluida ? 'btn-warning' : 'btn-success' }}">
                        {{ $pedidoAtual?->captacao_concluida ? 'Reabrir pedido' : 'Finalizar pedido' }}
                    </button>
                </form>
            @endif
            <div class="ms-auto d-flex align-items-center gap-2">
                <label for="numero-pedido-loja" class="form-label mb-0 small text-nowrap text-muted">Nº pedido</label>
                @if ($podeEditar ?? false)
                    <input type="text"
                           id="numero-pedido-loja"
                           class="form-control form-control-sm captacao-pedido-loja-numero"
                           maxlength="60"
                           placeholder="Digite o nº"
                           autocomplete="off"
                           data-url="{{ route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $cliente]) }}"
                           value="{{ $pedidoAtual?->numero_pedido ?? '' }}">
                @elseif ($pedidoAtual?->numero_pedido)
                    <span class="small fw-semibold text-nowrap">{{ $pedidoAtual->numero_pedido }}</span>
                @else
                    <span class="small text-muted">—</span>
                @endif
            </div>
        </div>

        @if (! $possuiFrutas)
            <div class="alert alert-warning py-2 small mb-2">
                Sem frutas vinculadas.
                <a href="{{ route('admin.captacao.frutas-por-loja.show', $cliente) }}">Frutas por loja</a>
            </div>
        @endif

        @if ($possuiFrutas)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-1">
                    <span>
                        <strong>Hoje</strong>
                        <span class="text-muted">{{ $lote->data_referencia?->format('d/m/Y') }}</span>
                    </span>
                    @if ($cliente->percentual_margem_alvo !== null)
                        <span class="badge bg-light text-dark">Alvo {{ $cliente->percentual_margem_alvo }}%</span>
                    @endif
                    @if ((float) $cliente->desconto_nf > 0)
                        <span class="badge bg-light text-dark" title="Desconto NF aplicado na rentabilidade">
                            Desc. NF {{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}%
                        </span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Fruta</th>
                            <th class="text-end" style="width:4.5rem">Qtd</th>
                            <th class="text-end captacao-custo-sub" title="Preço médio no estoque da unidade de saída">PM saída</th>
                            <th class="text-end captacao-custo-sub" title="Custo operacional da unidade de faturamento (na UM da fruta)">CO fatur.</th>
                            <th class="text-end">Custo</th>
                            <th class="text-end" style="width:5rem">Venda</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($linhas as $i => $linha)
                            @php
                                $custoDet = $linha['custo_detalhe'];
                            @endphp
                            <tr class="captacao-pedido-loja-linha" data-fruta-id="{{ $linha['fruta']->id }}">
                                <td class="text-truncate" style="max-width:12rem" title="{{ $linha['fruta']->nome }}">
                                    {{ $linha['fruta']->nome }}
                                    <span class="text-muted">({{ $linha['fruta']->unidade_medicao }})</span>
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.001" min="0" name="itens[{{ $i }}][quantidade]"
                                           form="form-pedido-loja"
                                           class="form-control form-control-sm text-end captacao-pedido-loja-input captacao-pedido-loja-qty"
                                           value="{{ $linha['item_atual'] ? rtrim(rtrim($linha['item_atual']->quantidade, '0'), '.') : '' }}"
                                           @disabled(! $podeEditar)>
                                    <input type="hidden" name="itens[{{ $i }}][id_fruta]" form="form-pedido-loja" value="{{ $linha['fruta']->id }}">
                                </td>
                                <td class="text-end text-muted text-nowrap captacao-custo-sub captacao-pedido-loja-pm">
                                    @if ($custoDet['pm_um'] !== null)
                                        <span class="captacao-custo-num">{{ number_format((float) $custoDet['pm_um'], 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-warning" title="Sem estoque na unidade de saída">s/ est.</span>
                                    @endif
                                </td>
                                <td class="text-end text-muted text-nowrap captacao-custo-sub captacao-pedido-loja-co">
                                    @if ($custoDet['eh_saida_hub'] && $custoDet['pm_um'] !== null)
                                        <span class="captacao-custo-num">{{ number_format((float) ($custoDet['co_um'] ?? 0), 2, ',', '.') }}</span>
                                        @if ($custoDet['co_kg'] !== null && (float) $custoDet['co_kg'] > 0)
                                            <span class="d-none d-md-inline text-muted" title="CO por kg">· {{ number_format((float) $custoDet['co_kg'], 2, ',', '.') }}/kg</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap captacao-pedido-loja-custo">
                                    @if ($custoDet['custo_final'] !== null)
                                        <strong class="captacao-custo-num">{{ number_format((float) $custoDet['custo_final'], 2, ',', '.') }}</strong>
                                    @else
                                        <span class="text-warning" title="Sem estoque na unidade de saída">s/ est.</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.01" min="0" name="itens[{{ $i }}][preco_venda]"
                                           form="form-pedido-loja"
                                           class="form-control form-control-sm text-end captacao-pedido-loja-input captacao-pedido-loja-preco"
                                           value="{{ $linha['item_atual']?->preco_venda !== null ? rtrim(rtrim($linha['item_atual']->preco_venda, '0'), '.') : '' }}"
                                           @disabled(! $podeEditar)>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

@if ($podeEditar)
@push('scripts')
<script>
(function () {
    const form = document.getElementById('form-pedido-loja');
    const badge = document.getElementById('pedido-loja-sync-badge');
    const numeroInput = document.getElementById('numero-pedido-loja');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!form && !numeroInput) return;

    let debounceTimer = null;
    let salvando = false;
    let salvarNovamente = false;
    let linhaPendente = null;
    let pularSalvarAntesConclusao = false;

    function setBadge(estado, texto) {
        if (!badge) return;
        badge.classList.remove('d-none', 'sincronizando', 'sincronizado', 'erro', 'pendente', 'bg-secondary');
        badge.classList.add(estado);
        badge.textContent = texto;
    }

    function linhaDoInput(input) {
        return input?.closest('tr.captacao-pedido-loja-linha');
    }

    function marcarLinha(linha, ok) {
        if (!linha) return;
        linha.querySelectorAll('.captacao-pedido-loja-input').forEach((inp) => {
            inp.classList.remove('is-valid', 'is-invalid');
            inp.classList.add(ok ? 'is-valid' : 'is-invalid');
        });
    }

    async function mensagemErro(res) {
        const data = await res.json().catch(() => ({}));
        if (data.message) return data.message;
        if (data.errors) return Object.values(data.errors).flat().join(' ');
        return 'Não foi possível salvar o pedido.';
    }

    async function salvarPedido(linhaFeedback) {
        if (!form) {
            return true;
        }

        if (salvando) {
            salvarNovamente = true;
            return new Promise((resolve) => {
                const aguardar = () => {
                    if (!salvando) {
                        resolve();
                        return;
                    }
                    setTimeout(aguardar, 50);
                };
                aguardar();
            });
        }

        salvando = true;
        let sucesso = true;

        do {
            salvarNovamente = false;
            setBadge('sincronizando', 'Salvando…');

            const linha = linhaFeedback ?? linhaPendente;
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: data,
                });

                if (!res.ok) {
                    marcarLinha(linha, false);
                    setBadge('erro', 'Erro ao salvar');
                    sucesso = false;
                    if (typeof window.AdminConfirm?.alert === 'function') {
                        window.AdminConfirm.alert({
                            title: 'Captação',
                            message: await mensagemErro(res),
                            variant: 'warning',
                            confirmLabel: 'Entendi',
                        });
                    }
                    break;
                }

                marcarLinha(linha, true);
                setBadge('sincronizado', 'Salvo');
            } catch (e) {
                marcarLinha(linha, false);
                setBadge('erro', 'Erro ao salvar');
                sucesso = false;
                break;
            }
        } while (salvarNovamente);

        salvando = false;
        if (!salvarNovamente) {
            linhaPendente = null;
        }

        return sucesso;
    }

    function limparPrecoPedidoLojaSeQuantidadeVazia(input) {
        if (!input?.classList?.contains('captacao-pedido-loja-qty') || input.value !== '') {
            return;
        }
        const linha = linhaDoInput(input);
        const preco = linha?.querySelector('.captacao-pedido-loja-preco');
        if (preco) {
            preco.value = '';
        }
    }

    function agendarSalvo(input) {
        limparPrecoPedidoLojaSeQuantidadeVazia(input);
        linhaPendente = linhaDoInput(input);
        setBadge('pendente', 'Alterado');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => salvarPedido(), 450);
    }

    document.querySelectorAll('.captacao-pedido-loja-input:not(:disabled)').forEach((input) => {
        input.addEventListener('input', () => agendarSalvo(input));
        input.addEventListener('change', () => agendarSalvo(input));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                linhaPendente = linhaDoInput(input);
                salvarPedido(linhaPendente);
            }
        });
    });

    async function salvarNumeroPedido(input) {
        const url = input.dataset.url;
        if (!url || input.disabled) return;

        setBadge('sincronizando', 'Salvando…');

        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ numero_pedido: input.value.trim() || null }),
            });

            if (!res.ok) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                setBadge('erro', 'Erro ao salvar');
                if (typeof window.AdminConfirm?.alert === 'function') {
                    window.AdminConfirm.alert({
                        title: 'Nº pedido',
                        message: await mensagemErro(res),
                        variant: 'warning',
                        confirmLabel: 'Entendi',
                    });
                }
                return;
            }

            const data = await res.json().catch(() => ({}));
            if (typeof data.numero_pedido === 'string') {
                input.value = data.numero_pedido;
            }
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            setBadge('sincronizado', 'Salvo');
        } catch (e) {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            setBadge('erro', 'Erro ao salvar');
        }
    }

    if (numeroInput) {
        let debounceNumero = null;
        numeroInput.addEventListener('input', () => {
            numeroInput.classList.remove('is-valid', 'is-invalid');
            setBadge('pendente', 'Alterado');
            clearTimeout(debounceNumero);
            debounceNumero = setTimeout(() => salvarNumeroPedido(numeroInput), 450);
        });
        numeroInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceNumero);
                salvarNumeroPedido(numeroInput);
            }
        });
        numeroInput.addEventListener('blur', () => {
            clearTimeout(debounceNumero);
            salvarNumeroPedido(numeroInput);
        });
    }

    const formConclusao = document.getElementById('form-pedido-loja-conclusao');
    if (formConclusao) {
        formConclusao.addEventListener('submit', async (event) => {
            if (pularSalvarAntesConclusao) {
                pularSalvarAntesConclusao = false;
                return;
            }

            event.preventDefault();
            clearTimeout(debounceTimer);

            const btn = formConclusao.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            if (numeroInput) {
                await salvarNumeroPedido(numeroInput);
            }

            if (form) {
                const ok = await salvarPedido();
                if (!ok) {
                    if (btn) btn.disabled = false;
                    return;
                }
            }

            pularSalvarAntesConclusao = true;
            formConclusao.submit();
        });
    }

    const cardSaida = document.getElementById('card-saida-fisica-loja');
    const statusSaida = document.querySelector('.captacao-pedido-loja-saida-status');
    const checksSaida = document.querySelectorAll('.captacao-pedido-loja-saida-check:not(:disabled)');

    function formatarMoedaBr(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return null;
        }
        const n = Number(valor);
        if (Number.isNaN(n)) {
            return null;
        }
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function htmlSemEstoque() {
        return '<span class="text-warning" title="Sem estoque na unidade de saída">s/ est.</span>';
    }

    function atualizarCustosNaTabela(custos) {
        if (!custos || typeof custos !== 'object') return;
        Object.entries(custos).forEach(([idFruta, detalhe]) => {
            const linha = document.querySelector(`tr.captacao-pedido-loja-linha[data-fruta-id="${idFruta}"]`);
            if (!linha) return;

            const celPm = linha.querySelector('.captacao-pedido-loja-pm');
            const celCo = linha.querySelector('.captacao-pedido-loja-co');
            const celFinal = linha.querySelector('.captacao-pedido-loja-custo');

            const pm = formatarMoedaBr(detalhe?.pm);
            const co = formatarMoedaBr(detalhe?.co);
            const final = formatarMoedaBr(detalhe?.final);

            if (celPm) {
                celPm.innerHTML = pm !== null
                    ? `<span class="captacao-custo-num">${pm}</span>`
                    : htmlSemEstoque();
            }
            if (celCo) {
                celCo.innerHTML = co !== null
                    ? `<span class="captacao-custo-num">${co}</span>`
                    : '<span class="text-muted">—</span>';
            }
            if (celFinal) {
                celFinal.innerHTML = final !== null
                    ? `<strong class="captacao-custo-num">${final}</strong>`
                    : htmlSemEstoque();
            }
        });
    }

    async function salvarSaidaFisica(check) {
        const url = check.dataset.url;
        if (!url) return;

        cardSaida?.classList.add('salvando');
        if (statusSaida) statusSaida.textContent = '…';

        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    id_unidade_negocio_saida_venda: parseInt(check.value, 10),
                }),
            });

            if (!res.ok) {
                if (statusSaida) statusSaida.textContent = 'Erro ao salvar saída.';
                if (typeof window.AdminConfirm?.alert === 'function') {
                    window.AdminConfirm.alert({
                        title: 'Saída estoque físico',
                        message: await mensagemErro(res),
                        variant: 'warning',
                        confirmLabel: 'Entendi',
                    });
                }
                return;
            }

            const data = await res.json().catch(() => ({}));
            atualizarCustosNaTabela(data.custos);
            if (statusSaida) statusSaida.textContent = '';
        } catch (e) {
            if (statusSaida) statusSaida.textContent = 'Erro ao salvar saída.';
        } finally {
            cardSaida?.classList.remove('salvando');
        }
    }

    checksSaida.forEach((check) => {
        check.addEventListener('change', async () => {
            if (!check.checked) {
                check.checked = true;
                return;
            }
            checksSaida.forEach((outro) => {
                if (outro !== check) outro.checked = false;
            });
            await salvarSaidaFisica(check);
        });
    });
})();
</script>
@endpush
@endif
