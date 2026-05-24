@extends('layouts.app')

@section('title', 'Matriz de captação')
@section('page-title', 'Matriz — '.$lote->unidadeGalpao->nome)

@push('head')
    <style>
        #captacao-matriz {
            table-layout: fixed;
            --captacao-matriz-col-zebra: #eef0f8;
            --captacao-matriz-bloqueada: #e4e5f8;
            --captacao-matriz-bloqueada-zebra: #d8dbf0;
        }

        [data-bs-theme='dark'] #captacao-matriz {
            --captacao-matriz-col-zebra: #2a2d3d;
            --captacao-matriz-bloqueada: #343a55;
            --captacao-matriz-bloqueada-zebra: #3d4460;
        }

        #captacao-matriz .captacao-matriz-col-zebra {
            --highdmin-table-bg: var(--captacao-matriz-col-zebra);
            --highdmin-table-accent-bg: var(--captacao-matriz-col-zebra);
            background-color: var(--captacao-matriz-col-zebra) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-col-zebra) !important;
            border-color: var(--captacao-matriz-col-zebra) !important;
        }

        #captacao-matriz thead th.captacao-matriz-col-loja {
            width: 12rem;
            min-width: 10rem;
        }

        #captacao-matriz thead th.captacao-matriz-col-fruta {
            vertical-align: bottom;
            text-align: center;
            width: 2.75rem;
            min-width: 2.75rem;
            height: auto;
            padding: 0.5rem 0.25rem;
            overflow: visible;
        }

        #captacao-matriz .captacao-matriz-fruta-nome {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            overflow: visible;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.15;
        }

        #captacao-matriz tbody td:not(:first-child) {
            width: 2.75rem;
            max-width: 3rem;
            padding: 0.2rem;
            vertical-align: middle;
        }

        #captacao-matriz .captacao-celula {
            min-width: 2.25rem;
            padding: 0.15rem 0.2rem;
            text-align: center;
        }

        #captacao-matriz td.captacao-matriz-celula-bloqueada {
            --highdmin-table-bg: var(--captacao-matriz-bloqueada);
            --highdmin-table-accent-bg: var(--captacao-matriz-bloqueada);
            background-color: var(--captacao-matriz-bloqueada) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-bloqueada) !important;
            border-color: var(--captacao-matriz-bloqueada) !important;
            color: #313a46;
            text-align: center;
            vertical-align: middle;
        }

        #captacao-matriz td.captacao-matriz-celula-bloqueada.captacao-matriz-col-zebra {
            background-color: var(--captacao-matriz-bloqueada-zebra) !important;
            box-shadow: inset 0 0 0 9999px var(--captacao-matriz-bloqueada-zebra) !important;
            border-color: var(--captacao-matriz-bloqueada-zebra) !important;
        }

        [data-bs-theme='dark'] #captacao-matriz td.captacao-matriz-celula-bloqueada {
            color: var(--highdmin-body-color);
        }

        #captacao-matriz .captacao-matriz-col-zebra .captacao-celula {
            background-color: #fff;
        }

        [data-bs-theme='dark'] #captacao-matriz .captacao-matriz-col-zebra .captacao-celula {
            background-color: var(--highdmin-secondary-bg);
        }

        #captacao-matriz .captacao-matriz-sem-vinculo {
            display: inline-block;
            color: var(--bs-danger);
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1;
        }
    </style>
@endpush

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', ['lote' => $lote])

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <p class="mb-0"><strong>Lote #{{ $lote->id }}</strong> — {{ $lote->data_referencia->format('d/m/Y') }} — {{ $lote->status->label() }}</p>
                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                    <span class="badge bg-secondary" id="matriz-sync-badge">sincronizado</span>
                @endif
                <a href="{{ route('admin.captacao.lotes.show', $lote) }}" class="btn btn-sm btn-light" title="Romaneios e detalhes do lote">
                    <i class="ri-eye-line me-1"></i> Ver lote
                </a>
                @canany(['captacao.cliente_fruta.vincular', 'captacao.pedido.editar', 'captacao.lote.visualizar'])
                    <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $lote->id_unidade_negocio_faturamento]) }}" class="btn btn-sm btn-soft-primary">Frutas por loja</a>
                @endcanany
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::AguardandoVinculoFrete)
                    @can('captacao.lote.frete.vincular')
                        <a href="{{ route('admin.captacao.lotes.fretes.index', $lote) }}" class="btn btn-sm btn-soft-warning">Vincular frete (opcional)</a>
                    @endcan
                @endif
                @include('admin.captacao._lote-pipeline-acoes', ['lote' => $lote])
            </div>
        </div>
        @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
            <div class="card-footer py-2 text-muted small">
                Selecione a loja na última linha · ↑↓ na célula · atualização a cada 2s
            </div>
        @endif
    </div>

    @if ($clientes->isEmpty() && $clientesDisponiveis->isEmpty())
        <div class="alert alert-warning">
            Nenhuma loja com frutas vinculadas neste faturamento.
            <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $lote->id_unidade_negocio_faturamento]) }}">Configure frutas por loja</a>
            antes de montar a captação.
        </div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm" id="captacao-matriz">
                <thead>
                <tr id="matriz-header-row">
                    <th class="captacao-matriz-col-loja">Loja</th>
                    @foreach ($frutas as $fruta)
                        <th @class(['captacao-matriz-col-fruta', 'captacao-matriz-col-zebra' => $loop->odd]) data-fruta-id="{{ $fruta->id }}">
                            <span class="captacao-matriz-fruta-nome" title="{{ $fruta->nome }}">{{ $fruta->nome }}</span>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody id="matriz-body">
                @foreach ($clientes as $cliente)
                    @php
                        $frutasCliente = $frutasPorCliente[$cliente->id] ?? [];
                    @endphp
                    <tr class="matriz-row-loja" data-cliente-id="{{ $cliente->id }}">
                        <td class="text-nowrap fw-semibold">{{ $cliente->fantasia ?: $cliente->razao_social }}</td>
                        @foreach ($frutas as $fruta)
                            @php
                                $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
                                $item = $pedido?->itens->firstWhere('id_fruta', $fruta->id);
                                $podeEditar = in_array($fruta->id, $frutasCliente, true);
                            @endphp
                            <td @class([
                                'captacao-matriz-celula-bloqueada' => ! $podeEditar,
                                'captacao-matriz-col-zebra' => $loop->odd,
                            ])>
                                @if ($podeEditar)
                                    <input type="number"
                                           class="form-control form-control-sm captacao-celula"
                                           step="1"
                                           min="0"
                                           data-lote="{{ $lote->id }}"
                                           data-cliente="{{ $cliente->id }}"
                                           data-fruta="{{ $fruta->id }}"
                                           data-version="{{ $item?->version ?? '' }}"
                                           value="{{ $item ? (int) $item->quantidade : '' }}">
                                @else
                                    <span class="captacao-matriz-sem-vinculo" title="Fruta não vinculada a esta loja" aria-label="Sem vínculo">×</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                <tr id="matriz-row-adicionar" class="table-light">
                    <td>
                        <select id="select-nova-loja" class="form-select form-select-sm" @disabled($clientesDisponiveis->isEmpty())>
                            <option value="">Selecione a loja…</option>
                            @foreach ($clientesDisponiveis as $disponivel)
                                <option value="{{ $disponivel->id }}">{{ $disponivel->fantasia ?: $disponivel->razao_social }}</option>
                            @endforeach
                        </select>
                    </td>
                    @foreach ($frutas as $fruta)
                        <td @class(['captacao-matriz-celula-bloqueada', 'captacao-matriz-col-zebra' => $loop->odd])></td>
                    @endforeach
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const urlCelula = @json(route('admin.captacao.lotes.celula.update', $lote));
    const urlEstado = @json(route('admin.captacao.lotes.matriz.estado', $lote));
    const urlAdicionarLoja = @json(route('admin.captacao.lotes.matriz.adicionar-loja', $lote));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const badge = document.getElementById('matriz-sync-badge');
    const selectLoja = document.getElementById('select-nova-loja');
    let matrizVersion = null;
    let layoutHash = @json($layoutHash);
    let salvando = false;

    function setBadge(estado) {
        if (!badge) return;
        badge.textContent = estado;
        badge.className = 'badge ' + ({
            sincronizado: 'bg-success',
            pendente: 'bg-warning text-dark',
            sincronizando: 'bg-info',
            erro: 'bg-danger',
        }[estado] || 'bg-secondary');
    }

    async function mensagemErroResposta(res) {
        const data = await res.json().catch(() => ({}));
        if (data.message) {
            return data.message;
        }
        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }

        return 'Não foi possível concluir a operação.';
    }

    function mostrarErro(mensagem, titulo = 'Atenção') {
        if (typeof window.AdminConfirm?.alert === 'function') {
            const ok = window.AdminConfirm.alert({
                title: titulo,
                message: mensagem,
                variant: 'warning',
                confirmLabel: 'Entendi',
            });

            if (ok !== false) {
                return;
            }
        }

        console.error('[Captacao matriz]', titulo, mensagem);
    }

    function headersJson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        };
    }

    selectLoja?.addEventListener('change', async function () {
        const idCliente = this.value;
        if (!idCliente) return;

        setBadge('sincronizando');
        this.disabled = true;

        try {
            const res = await fetch(urlAdicionarLoja, {
                method: 'POST',
                headers: headersJson(),
                body: JSON.stringify({ id_cliente: Number(idCliente) }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível adicionar a loja');
                this.disabled = false;
                this.value = '';
                setBadge('erro');
                return;
            }

            window.location.reload();
        } catch (e) {
            mostrarErro('Erro ao adicionar loja.');
            this.disabled = false;
            this.value = '';
            setBadge('erro');
        }
    });

    async function salvarCelula(input, payload) {
        setBadge('sincronizando');
        salvando = true;
        try {
            const res = await fetch(urlCelula, {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                input.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res), 'Captação indisponível');
                return;
            }
            const data = await res.json();
            input.dataset.version = data.item.version;
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            setBadge('sincronizado');
        } catch (e) {
            input.classList.add('is-invalid');
            setBadge('erro');
        } finally {
            salvando = false;
        }
    }

    function payloadFromInput(input) {
        return {
            id_cliente: Number(input.dataset.cliente),
            id_fruta: Number(input.dataset.fruta),
            quantidade: input.value === '' ? 0 : Number(input.value),
            version: input.dataset.version ? Number(input.dataset.version) : null,
        };
    }

    function bindCelulas() {
        document.querySelectorAll('.captacao-celula').forEach((input) => {
            if (input.dataset.bound) return;
            input.dataset.bound = '1';

            input.addEventListener('change', () => {
                setBadge('pendente');
                salvarCelula(input, payloadFromInput(input));
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const delta = e.key === 'ArrowUp' ? 1 : -1;
                    const atual = input.value === '' ? 0 : Number(input.value);
                    input.value = Math.max(0, atual + delta);
                    setBadge('pendente');
                    salvarCelula(input, {
                        id_cliente: Number(input.dataset.cliente),
                        id_fruta: Number(input.dataset.fruta),
                        incremento: delta,
                        version: input.dataset.version ? Number(input.dataset.version) : null,
                    });
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });
    }

    bindCelulas();

    async function pollEstado() {
        if (salvando) return;
        try {
            const res = await fetch(urlEstado, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.layout_hash && data.layout_hash !== layoutHash) {
                window.location.reload();
                return;
            }

            if (matrizVersion !== null && matrizVersion !== data.version) {
                aplicarEstado(data);
            }
            matrizVersion = data.version;
        } catch (e) { /* ignore */ }
    }

    function aplicarEstado(data) {
        Object.entries(data.celulas || {}).forEach(([key, cel]) => {
            const [clienteId, frutaId] = key.split('_');
            const input = document.querySelector(
                `.captacao-celula[data-cliente="${clienteId}"][data-fruta="${frutaId}"]`
            );
            if (!input || document.activeElement === input) return;
            input.value = cel.quantidade > 0 ? Number(cel.quantidade) : '';
            input.dataset.version = cel.version;
        });
    }

    pollEstado();
    setInterval(pollEstado, 2000);
})();
</script>
@endpush
