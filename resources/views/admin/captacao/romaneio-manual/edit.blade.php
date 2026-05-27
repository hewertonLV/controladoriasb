@extends('layouts.app')

@section('title', 'Solicitar transferência')
@section('page-title', 'Solicitar transferência #'.$lote->id)

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', [
        'lote' => $lote,
        'proximaAcao' => $proximaAcao,
        'exibirRomaneioSyncBadge' => $editavel,
    ])

    @if ($editavel)
        <div class="card mb-3">
            <div class="card-header"><strong>Adicionar fruta</strong></div>
            <div class="card-body row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="select-nova-fruta">Fruta</label>
                    <select id="select-nova-fruta"
                            class="form-select"
                            data-search-select
                            data-placeholder="Selecione ou pesquise a fruta">
                        <option value="">Selecione a fruta…</option>
                        @foreach ($frutas as $fruta)
                            <option value="{{ $fruta->id }}">{{ $fruta->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="select-origem-fruta">Origem física (HUB)</label>
                    <select id="select-origem-fruta"
                            class="form-select"
                            data-search-select
                            data-placeholder="Selecione ou pesquise o HUB de origem">
                        <option value="">Selecione…</option>
                        @foreach ($hubs as $hub)
                            <option value="{{ $hub->id }}">{{ $hub->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="motivo-nova-fruta">Motivo</label>
                    <input type="text" id="motivo-nova-fruta" class="form-control" placeholder="Reposição estoque">
                </div>
            </div>
            <div class="card-footer text-muted small">
                Ao escolher fruta e origem, a linha entra no romaneio. Use ↑↓ nas caixas para incrementar; o valor salva automaticamente.
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>Linhas do romaneio</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0" id="tabela-romaneio-manual">
                <thead>
                <tr>
                    <th>Fruta</th>
                    <th class="text-center" style="width: 8rem;">Caixas</th>
                    <th>Origem física</th>
                    <th>Motivo</th>
                </tr>
                </thead>
                <tbody id="romaneio-linhas-body">
                @forelse ($lote->manualLinhas as $linha)
                    <tr data-linha-id="{{ $linha->id }}">
                        <td class="fw-semibold">{{ $linha->fruta->nome }}</td>
                        <td class="text-center">
                            @if ($editavel)
                                <input type="number"
                                       class="form-control form-control-sm romaneio-caixa text-center"
                                       step="1"
                                       min="0"
                                       data-linha="{{ $linha->id }}"
                                       value="{{ (int) $linha->quantidade > 0 ? (int) $linha->quantidade : '' }}">
                            @else
                                {{ (int) $linha->quantidade }}
                            @endif
                        </td>
                        <td>{{ $linha->unidadeOrigemFisica->nome }}</td>
                        <td>{{ $linha->motivo ?? '—' }}</td>
                    </tr>
                @empty
                    <tr id="romaneio-sem-linhas">
                        <td colspan="4" class="text-muted py-4">Nenhuma fruta no romaneio. @if ($editavel) Selecione acima para adicionar. @endif</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Romaneio 2 — Abastecimento (prévia)</strong></div>
        <div class="card-body table-responsive">
            @include('admin.captacao._romaneio-abastecimento-tabela', ['romaneioAbastecimento' => $romaneioAbastecimento])
        </div>
    </div>
@endsection

@if ($editavel)
@include('admin.captacao._search-select-scripts')

@push('scripts')
<script>
(function () {
    const urlAdicionarFruta = @json(route('admin.captacao.romaneio-manual.frutas.store', $lote));
    const urlAtualizarLinha = @json(route('admin.captacao.romaneio-manual.linhas.update', ['lote' => $lote, 'linha' => '__LINHA__']));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const badge = document.getElementById('romaneio-sync-badge');
    const tbody = document.getElementById('romaneio-linhas-body');
    const selectFruta = document.getElementById('select-nova-fruta');
    const selectOrigem = document.getElementById('select-origem-fruta');
    const inputMotivo = document.getElementById('motivo-nova-fruta');
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

    function headersJson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        };
    }

    async function mensagemErroResposta(res) {
        const data = await res.json().catch(() => ({}));
        if (data.message) return data.message;
        if (data.errors) return Object.values(data.errors).flat().join(' ');

        return 'Não foi possível concluir a operação.';
    }

    function mostrarErro(mensagem, titulo = 'Atenção') {
        if (typeof window.AdminConfirm?.alert === 'function') {
            window.AdminConfirm.alert({
                title: titulo,
                message: mensagem,
                variant: 'warning',
                confirmLabel: 'Entendi',
            });
            return;
        }
        console.error('[Solicitar transferência]', titulo, mensagem);
    }

    function urlLinha(id) {
        return urlAtualizarLinha.replace('__LINHA__', String(id));
    }

    function removerLinhaVazia() {
        document.getElementById('romaneio-sem-linhas')?.remove();
    }

    function appendLinha(linha) {
        removerLinhaVazia();
        const tr = document.createElement('tr');
        tr.dataset.linhaId = String(linha.id);
        tr.innerHTML = `
            <td class="fw-semibold">${linha.fruta_nome}</td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm romaneio-caixa text-center"
                       step="1" min="0" data-linha="${linha.id}" value="">
            </td>
            <td>${linha.origem_nome}</td>
            <td>${linha.motivo || '—'}</td>
        `;
        tbody.appendChild(tr);
        bindCaixas(tr);
    }

    async function adicionarFruta() {
        const idFruta = selectFruta?.value;
        const idOrigem = selectOrigem?.value;
        if (!idFruta || !idOrigem) return;

        setBadge('sincronizando');
        selectFruta.disabled = true;
        selectOrigem.disabled = true;

        try {
            const res = await fetch(urlAdicionarFruta, {
                method: 'POST',
                headers: headersJson(),
                body: JSON.stringify({
                    id_fruta: Number(idFruta),
                    id_unidade_origem_fisica: Number(idOrigem),
                    motivo: inputMotivo?.value?.trim() || null,
                }),
            });

            if (!res.ok) {
                mostrarErro(await mensagemErroResposta(res), 'Não foi possível adicionar a fruta');
                setBadge('erro');
                return;
            }

            const data = await res.json();
            appendLinha(data.linha);
            selectFruta.value = '';
            selectOrigem.value = '';
            setBadge('sincronizado');
            window.location.reload();
        } catch (e) {
            mostrarErro('Erro ao adicionar fruta.');
            setBadge('erro');
        } finally {
            selectFruta.disabled = false;
            selectOrigem.disabled = false;
        }
    }

    function onSelectFrutaOrigem() {
        if (selectFruta?.value && selectOrigem?.value) {
            adicionarFruta();
        }
    }

    selectFruta?.addEventListener('change', onSelectFrutaOrigem);
    selectOrigem?.addEventListener('change', onSelectFrutaOrigem);

    async function salvarCaixa(input, payload) {
        setBadge('sincronizando');
        salvando = true;
        try {
            const res = await fetch(urlLinha(input.dataset.linha), {
                method: 'PATCH',
                headers: headersJson(),
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                input.classList.add('is-invalid');
                setBadge('erro');
                mostrarErro(await mensagemErroResposta(res), 'Romaneio indisponível');
                return;
            }
            await res.json();
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

    function bindCaixas(root) {
        (root || document).querySelectorAll('.romaneio-caixa').forEach((input) => {
            if (input.dataset.bound) return;
            input.dataset.bound = '1';

            input.addEventListener('change', () => {
                setBadge('pendente');
                salvarCaixa(input, {
                    quantidade: input.value === '' ? 0 : Number(input.value),
                });
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const delta = e.key === 'ArrowUp' ? 1 : -1;
                    const atual = input.value === '' ? 0 : Number(input.value);
                    input.value = Math.max(0, atual + delta);
                    setBadge('pendente');
                    salvarCaixa(input, { incremento: delta });
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });
    }

    bindCaixas();
})();
</script>
@endpush
@endif
