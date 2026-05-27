@php
    /** @var array{hub_nome: string, frutas: list<array{fruta_nome: string, unidade_medicao: string, estoque_um: string, estoque_kg: string, falta_um: string, falta_kg: string}>} $nfEstoqueHubFaltas */
    $qtdFrutas = count($nfEstoqueHubFaltas['frutas']);
@endphp
<div class="modal fade"
     id="modal-nf-estoque-hub-insuficiente"
     tabindex="-1"
     aria-labelledby="modal-nf-estoque-hub-insuficiente-titulo"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2 px-3 border-0 pb-0">
                <div class="pe-2">
                    <h5 class="modal-title text-danger mb-0 fs-6" id="modal-nf-estoque-hub-insuficiente-titulo">
                        <i class="ri-error-warning-line me-1"></i> Estoque insuficiente
                    </h5>
                    <p class="text-muted mb-0 mt-1" style="font-size: 0.8rem;">
                        HUB <strong>{{ $nfEstoqueHubFaltas['hub_nome'] }}</strong> sem saldo para a NF.
                    </p>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <span class="text-muted" style="font-size: 0.8rem;">
                        {{ $qtdFrutas }} {{ $qtdFrutas === 1 ? 'fruta' : 'frutas' }} em falta
                    </span>
                    <button type="button"
                            class="btn btn-sm btn-soft-secondary py-0 px-2"
                            id="btn-toggle-nf-estoque-hub-lista"
                            aria-expanded="false"
                            aria-controls="nf-estoque-hub-lista-wrap"
                            title="Ver lista de frutas">
                        <i class="ri-eye-line fs-5" id="icon-nf-estoque-hub-lista" aria-hidden="true"></i>
                        <span class="visually-hidden">Ver lista de frutas</span>
                    </button>
                </div>
                <div id="nf-estoque-hub-lista-wrap" class="d-none">
                    <div class="table-responsive" style="max-height: 12rem;">
                        <table class="table table-sm table-bordered align-middle mb-0 caption-top small">
                            <thead class="table-light">
                            <tr class="text-nowrap">
                                <th class="py-1 px-2">Fruta</th>
                                <th class="text-end py-1 px-2">Estoque</th>
                                <th class="text-end py-1 px-2">Falta</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($nfEstoqueHubFaltas['frutas'] as $linha)
                                <tr>
                                    <td class="py-1 px-2 text-truncate" style="max-width: 7rem;" title="{{ $linha['fruta_nome'] }}">
                                        {{ $linha['fruta_nome'] }}
                                    </td>
                                    <td class="text-end py-1 px-2 text-nowrap">
                                        {{ $linha['estoque_um'] }} {{ $linha['unidade_medicao'] }}
                                        <span class="text-muted">· {{ $linha['estoque_kg'] }} kg</span>
                                    </td>
                                    <td class="text-end py-1 px-2 text-danger text-nowrap">
                                        {{ $linha['falta_um'] }} {{ $linha['unidade_medicao'] }}
                                        <span class="text-muted">· {{ $linha['falta_kg'] }} kg</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-muted mb-0 mt-2" style="font-size: 0.75rem;">
                    Ajuste o estoque no HUB ou revise os pedidos.
                </p>
            </div>
            <div class="modal-footer py-2 px-3 border-0 pt-0">
                <button type="button" class="btn btn-primary btn-sm w-100" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const btn = document.getElementById('btn-toggle-nf-estoque-hub-lista');
    const wrap = document.getElementById('nf-estoque-hub-lista-wrap');
    const icon = document.getElementById('icon-nf-estoque-hub-lista');
    if (!btn || !wrap || !icon) {
        return;
    }

    btn.addEventListener('click', function () {
        const aberto = !wrap.classList.contains('d-none');
        wrap.classList.toggle('d-none', aberto);
        btn.setAttribute('aria-expanded', aberto ? 'false' : 'true');
        icon.classList.toggle('ri-eye-line', aberto);
        icon.classList.toggle('ri-eye-off-line', !aberto);
        btn.title = aberto ? 'Ver lista de frutas' : 'Ocultar lista de frutas';
    });
})();
</script>
