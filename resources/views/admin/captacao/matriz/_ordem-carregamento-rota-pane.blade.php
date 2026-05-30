@php
    /** @var array<string, mixed> $grupoRota */
    /** @var string $aba */
    $abaRota = 'rota-'.$grupoRota['id_captacao_rota'];
    $ativa = $aba === $abaRota;
@endphp

<div class="tab-pane fade matriz-tab-pane-rota-vinculada {{ $ativa ? 'show active' : '' }}"
     id="matriz-tab-{{ $abaRota }}"
     role="tabpanel"
     data-rota-id="{{ $grupoRota['id_captacao_rota'] }}">
    <div class="card-body table-responsive pt-3 pb-3">
        <p class="text-muted small mb-2">
            Lojas com quantidade captada nesta rota. Defina a ordem de carregamento e use <strong>Concluir</strong> para impedir novos vínculos; <strong>Reabrir</strong> libera alterações novamente.
        </p>
        @include('admin.captacao.matriz._ordem-carregamento-rota-cabecalho', [
            'grupoRota' => $grupoRota,
            'lote' => $lote,
            'veiculos' => $veiculos,
            'rotas' => $rotas,
        ])
        <table class="table table-bordered table-sm align-middle matriz-ordem-rota-table">
            <thead>
            <tr>
                <th style="width:8rem">Ordem de Carregamento</th>
                <th style="min-width:11rem">Loja</th>
                <th>Item</th>
                <th class="text-end" style="width:6rem">Qtd (UM)</th>
            </tr>
            </thead>
            <tbody class="matriz-ordem-body" data-rota-id="{{ $grupoRota['id_captacao_rota'] }}">
                @include('admin.captacao.matriz._ordem-carregamento-rota-tbody', [
                    'grupoRota' => $grupoRota,
                    'lote' => $lote,
                ])
            </tbody>
        </table>
    </div>
</div>
