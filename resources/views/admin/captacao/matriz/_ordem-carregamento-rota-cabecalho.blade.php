@php
    /** @var array<string, mixed> $grupoRota */
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Veiculo> $veiculos */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\CaptacaoRota> $rotas */

    $rotaConcluida = (bool) ($grupoRota['concluida'] ?? false);
    $podeEditarRota = $lote->status->permiteEdicaoVinculoRota() && ! $rotaConcluida;
    $veiculosOcupadosOutrasRotas = $rotas
        ->filter(fn ($r) => $r->id_veiculo !== null && (int) $r->id !== (int) $grupoRota['id_captacao_rota'])
        ->pluck('id_veiculo')
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="matriz-rota-cabecalho mb-3">
    <div class="matriz-rota-titulo">
        <div class="matriz-rota-titulo-esq">
            <span class="fw-semibold">{{ $grupoRota['rota_nome'] }}</span>
            @if ($rotaConcluida)
                <span class="badge bg-success-subtle text-success">Concluída</span>
            @endif
        </div>
        <div class="matriz-rota-titulo-dir">
            @if ($podeEditarRota)
                @php
                    $podeConcluirRota = (bool) ($grupoRota['pode_concluir'] ?? false);
                    $pendenciasConclusao = $grupoRota['pendencias_conclusao'] ?? [];
                    $tituloConcluirRota = $podeConcluirRota
                        ? 'Concluir rota'
                        : 'Pendências para concluir a rota';
                @endphp
                <button type="button"
                        class="btn btn-soft-success btn-sm btn-matriz-icon btn-matriz-rota-concluir @if(! $podeConcluirRota) btn-matriz-rota-concluir--pendente @endif"
                        data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                        data-rota-nome="{{ $grupoRota['rota_nome'] }}"
                        data-url="{{ route('admin.captacao.lotes.rotas.concluir', [$lote, $grupoRota['id_captacao_rota']]) }}"
                        data-pendencias='@json($pendenciasConclusao, JSON_HEX_APOS | JSON_HEX_QUOT)'
                        title="{{ $tituloConcluirRota }}"
                        aria-label="{{ $tituloConcluirRota }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top">
                    <i class="ri-check-line" aria-hidden="true"></i>
                </button>
            @elseif ($rotaConcluida && $lote->status->permiteEdicaoVinculoRota())
                <a href="{{ route('admin.captacao.lotes.rotas.romaneio-pdf', [$lote, $grupoRota['id_captacao_rota']]) }}"
                   class="btn btn-soft-primary btn-sm btn-matriz-icon"
                   title="Baixar romaneio em PDF"
                   aria-label="Baixar romaneio em PDF"
                   data-bs-toggle="tooltip"
                   data-bs-placement="top">
                    <i class="ri-file-download-line" aria-hidden="true"></i>
                </a>
                <button type="button"
                        class="btn btn-soft-warning btn-sm btn-matriz-icon btn-matriz-rota-reabrir"
                        data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                        data-rota-nome="{{ $grupoRota['rota_nome'] }}"
                        data-url="{{ route('admin.captacao.lotes.rotas.reabrir', [$lote, $grupoRota['id_captacao_rota']]) }}"
                        title="Reabrir rota"
                        aria-label="Reabrir rota"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top">
                    <i class="ri-arrow-go-back-line" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </div>
    @if ($podeEditarRota)
        <div class="matriz-rota-cabecalho-campos">
            <input type="text"
                   class="form-control form-control-sm matriz-rota-motorista"
                   maxlength="120"
                   placeholder="Motorista"
                   data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                   data-url="{{ route('admin.captacao.lotes.rotas.motorista', [$lote, $grupoRota['id_captacao_rota']]) }}"
                   value="{{ $grupoRota['motorista_nome'] ?? '' }}">
            <select class="form-select form-select-sm matriz-rota-veiculo"
                    data-search-select
                    data-placeholder="Selecione ou pesquise o veículo"
                    data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                    data-url="{{ route('admin.captacao.lotes.rotas.veiculo', [$lote, $grupoRota['id_captacao_rota']]) }}">
                <option value="">Sem veículo vinculado</option>
                @foreach ($veiculos as $veiculo)
                    @if (in_array((int) $veiculo->id, $veiculosOcupadosOutrasRotas, true) && (int) ($grupoRota['id_veiculo'] ?? 0) !== (int) $veiculo->id)
                        @continue
                    @endif
                    <option value="{{ $veiculo->id }}"
                        @selected((int) ($grupoRota['id_veiculo'] ?? 0) === (int) $veiculo->id)>
                        {{ $veiculo->nome }} (SBS {{ $veiculo->id_sbs }})
                    </option>
                @endforeach
            </select>
        </div>
    @else
        <div class="matriz-rota-resumo small text-muted">
            @if (! empty($grupoRota['motorista_nome']))
                <div>Motorista: {{ $grupoRota['motorista_nome'] }}</div>
            @endif
            @if (! empty($grupoRota['veiculo_rotulo']))
                <div>Veículo: {{ $grupoRota['veiculo_rotulo'] }}</div>
            @endif
        </div>
    @endif
</div>
