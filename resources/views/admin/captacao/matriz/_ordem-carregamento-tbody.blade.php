@php
    /** @var list<array<string, mixed>> $gruposOrdemCarregamento */
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Veiculo> $veiculos */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\CaptacaoRota> $rotas */
@endphp

@if ($gruposOrdemCarregamento === [])
    <tr>
        <td colspan="5" class="text-muted text-center py-4">
            Nenhuma loja com rota e quantidade informada. Use as abas <strong>Quantidade</strong> e <strong>Rotas</strong> primeiro.
        </td>
    </tr>
@else
    @foreach ($gruposOrdemCarregamento as $grupoRota)
        @php
            $rotaRowspan = array_sum(array_map(fn (array $loja): int => count($loja['itens']), $grupoRota['lojas']));
            $rotaRenderizada = false;
            $totalLojas = (int) $grupoRota['total_lojas'];
            $veiculosOcupadosOutrasRotas = $rotas
                ->filter(fn ($r) => $r->id_veiculo !== null && (int) $r->id !== (int) $grupoRota['id_captacao_rota'])
                ->pluck('id_veiculo')
                ->map(fn ($id) => (int) $id)
                ->all();
        @endphp
        @foreach ($grupoRota['lojas'] as $loja)
            @php $lojaRowspan = count($loja['itens']); @endphp
            @foreach ($loja['itens'] as $idx => $item)
                <tr class="matriz-ordem-row"
                    data-rota-id="{{ $grupoRota['id_captacao_rota'] }}"
                    data-cliente-id="{{ $loja['id_cliente'] }}"
                    data-fruta-id="{{ $item['id_fruta'] }}">
                    @if (! $rotaRenderizada)
                        <td rowspan="{{ $rotaRowspan }}" class="align-top">
                            <div class="matriz-rota-cabecalho">
                                <span class="fw-semibold text-nowrap">{{ $grupoRota['rota_nome'] }}</span>
                                @if ($lote->status->permiteEdicaoVinculoRota())
                                    <div class="matriz-rota-cabecalho-campos">
                                        <input type="text"
                                               class="form-control form-control-sm matriz-rota-motorista"
                                               maxlength="120"
                                               placeholder="Motorista"
                                               data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                                               data-url="{{ route('admin.captacao.lotes.rotas.motorista', [$lote, $grupoRota['id_captacao_rota']]) }}"
                                               value="{{ $grupoRota['motorista_nome'] ?? '' }}">
                                        <select class="form-select form-select-sm matriz-rota-veiculo"
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
                                    @if (! empty($grupoRota['motorista_nome']))
                                        <span class="text-muted small">— {{ $grupoRota['motorista_nome'] }}</span>
                                    @endif
                                    @if (! empty($grupoRota['veiculo_rotulo']))
                                        <span class="text-muted small d-block">{{ $grupoRota['veiculo_rotulo'] }}</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        @php $rotaRenderizada = true; @endphp
                    @endif
                    @if ($idx === 0)
                        <td rowspan="{{ $lojaRowspan }}" class="align-top">
                            @if ($lote->status->permiteEdicaoVinculoRota())
                                <select class="form-select form-select-sm matriz-ordem-select"
                                        data-cliente="{{ $loja['id_cliente'] }}"
                                        data-rota="{{ $grupoRota['id_captacao_rota'] }}"
                                        data-total-lojas="{{ $totalLojas }}"
                                        data-url="{{ route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $loja['id_cliente']]) }}">
                                    <option value="">—</option>
                                    @for ($n = 1; $n <= $totalLojas; $n++)
                                        <option value="{{ $n }}"
                                                @selected((int) ($loja['ordem_carregamento'] ?? 0) === $n)>
                                            {{ $n }}
                                        </option>
                                    @endfor
                                </select>
                            @else
                                {{ $loja['ordem_carregamento'] ?? '—' }}
                            @endif
                        </td>
                        <td rowspan="{{ $lojaRowspan }}" class="align-top text-nowrap fw-semibold">
                            {{ $loja['loja_nome'] }}
                        </td>
                    @endif
                    <td>
                        {{ $item['fruta_nome'] }}
                        <span class="text-muted small">({{ $item['unidade_medicao'] }})</span>
                    </td>
                    <td class="text-end matriz-ordem-qty">
                        {{ rtrim(rtrim($item['quantidade'], '0'), '.') }}
                    </td>
                </tr>
            @endforeach
        @endforeach
    @endforeach
@endif
