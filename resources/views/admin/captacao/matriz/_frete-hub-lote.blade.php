@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, array{vinculo: \App\Models\Captacao\CaptacaoLoteMovimentacao, saida: \App\Models\Movimentacao|null, id_frete_atual: int|null}> $transferencias */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete> $fretesAbertos */
@endphp

<div class="card-body border-top-0 pt-4 pb-4" id="captacao-frete-hub-root">
    <p class="text-muted small mb-3">
        Transferências HUB × CD geradas na validação. Frete é <strong>opcional</strong> por linha — ao escolher, salva automaticamente.
        Se vinculou por engano, use <strong>Remover Frete</strong>.
    </p>

    <div class="border rounded p-3">
        <h6 class="mb-2">Transferências do lote</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Fruta</th>
                    <th>Qtd UM</th>
                    <th>Frete ABERTO</th>
                    <th class="text-nowrap" style="width:7rem">Situação</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($transferencias as $row)
                    @php
                        $idFreteAtual = $row['id_frete_atual'];
                    @endphp
                    <tr>
                        <td>{{ $row['vinculo']->fruta?->nome }}</td>
                        <td>{{ $row['saida']?->qtd_fruta_um ?? '—' }}</td>
                        <td>
                            @include('admin.captacao.matriz._frete-vinculo-campo', [
                                'url' => route('admin.captacao.lotes.fretes.transferencia', $lote),
                                'fretesAbertos' => $fretesAbertos,
                                'idFreteAtual' => $idFreteAtual,
                                'dataAttrs' => [
                                    'transferencia-origem-id' => $row['vinculo']->transferencia_origem_id,
                                ],
                            ])
                        </td>
                        <td>
                            <span class="captacao-frete-status small fw-semibold {{ $idFreteAtual ? 'text-success' : 'text-muted' }}">
                                {{ $idFreteAtual ? 'Vinculado' : 'Sem Frete' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">Nenhuma transferência vinculada a este lote.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('admin.captacao.matriz._frete-vinculo-scripts')
