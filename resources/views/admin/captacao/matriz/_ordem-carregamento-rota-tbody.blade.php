@php
    /** @var array<string, mixed> $grupoRota */
    /** @var \App\Models\Captacao\CaptacaoLote $lote */

    $rotaConcluida = (bool) ($grupoRota['concluida'] ?? false);
    $podeEditarRota = $lote->status->permiteEdicaoVinculoRota() && ! $rotaConcluida;
    $totalLojas = (int) $grupoRota['total_lojas'];
@endphp

@foreach ($grupoRota['lojas'] as $loja)
    @php $lojaRowspan = count($loja['itens']); @endphp
    @foreach ($loja['itens'] as $idx => $item)
        <tr class="matriz-ordem-row"
            data-rota-id="{{ $grupoRota['id_captacao_rota'] }}"
            data-cliente-id="{{ $loja['id_cliente'] }}"
            data-fruta-id="{{ $item['id_fruta'] }}">
            @if ($idx === 0)
                <td rowspan="{{ $lojaRowspan }}" class="align-top">
                    @if ($podeEditarRota)
                        <select class="form-select form-select-sm matriz-ordem-select"
                                data-search-select
                                data-placeholder="Ordem de carregamento"
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
