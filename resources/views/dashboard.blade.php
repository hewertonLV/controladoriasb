@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $totais = $dashboard['totais'];
        $cards = [
            [
                'label' => 'Unidades',
                'valor' => $totais['unidades'],
                'icone' => 'ri-building-4-line',
                'cor' => 'primary',
            ],
            [
                'label' => 'Clientes',
                'valor' => $totais['clientes'],
                'icone' => 'ri-group-line',
                'cor' => 'success',
            ],
            [
                'label' => 'Veículos',
                'valor' => $totais['veiculos'],
                'icone' => 'ri-truck-line',
                'cor' => 'warning',
            ],
            [
                'label' => 'Praças',
                'valor' => $totais['pracas'],
                'icone' => 'ri-map-pin-line',
                'cor' => 'info',
            ],
            [
                'label' => 'Estoques',
                'valor' => $totais['estoques'],
                'icone' => 'ri-archive-line',
                'cor' => 'secondary',
            ],
            [
                'label' => 'Movimentações',
                'valor' => $totais['movimentacoes'],
                'icone' => 'ri-exchange-funds-line',
                'cor' => 'danger',
                'detalhe' => $totais['movimentacoes_mes'].' neste mês',
            ],
        ];
    @endphp

    <div class="alert alert-light border d-flex align-items-start gap-2 mb-4" role="status">
        <i class="ri-dashboard-3-line fs-20 text-primary mt-1"></i>
        <div>
            <div class="fw-semibold">Resumo operacional</div>
            <div class="text-muted">{{ $dashboard['escopo_label'] }}</div>
            @if ($dashboard['acesso_total'])
                <small class="text-muted">Perfil com acesso a todas as unidades.</small>
            @endif
        </div>
    </div>

    <div class="row row-cols-xxl-3 row-cols-lg-2 row-cols-1 g-3 mb-4">
        @foreach ($cards as $card)
            <div class="col">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <h5 class="text-muted fs-13 fw-bold text-uppercase mb-2">{{ $card['label'] }}</h5>
                                <h3 class="my-0 fw-bold">{{ number_format($card['valor'], 0, ',', '.') }}</h3>
                                @if (! empty($card['detalhe']))
                                    <p class="mb-0 text-muted mt-2">
                                        <span class="text-nowrap">{{ $card['detalhe'] }}</span>
                                    </p>
                                @endif
                            </div>
                            <div class="avatar-lg flex-shrink-0">
                                <span class="avatar-title bg-{{ $card['cor'] }}-subtle text-{{ $card['cor'] }} rounded-circle fs-28">
                                    <i class="{{ $card['icone'] }}"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Cadastros por unidade</h4>
                </div>
                <div class="card-body p-0">
                    @if (count($dashboard['unidades']) === 0)
                        <p class="text-muted p-3 mb-0">Nenhuma unidade disponível para o seu usuário.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Unidade</th>
                                        <th class="text-end">Clientes</th>
                                        <th class="text-end">Veículos</th>
                                        <th class="text-end">Praças</th>
                                        <th class="text-end">Estoques</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dashboard['unidades'] as $unidade)
                                        <tr>
                                            <td class="fw-medium">{{ $unidade['nome'] }}</td>
                                            <td class="text-end">{{ number_format($unidade['clientes'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($unidade['veiculos'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($unidade['pracas'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($unidade['estoques'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Movimentações por tipo</h4>
                </div>
                <div class="card-body">
                    @if (count($dashboard['movimentacoes_por_tipo']) === 0)
                        <p class="text-muted mb-0">Nenhuma movimentação ativa no seu escopo.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($dashboard['movimentacoes_por_tipo'] as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <span>{{ $item['tipo'] }}</span>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill">
                                        {{ number_format($item['total'], 0, ',', '.') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Últimas movimentações</h4>
                </div>
                <div class="card-body p-0">
                    @if (count($dashboard['movimentacoes_recentes']) === 0)
                        <p class="text-muted p-3 mb-0">Sem movimentações recentes.</p>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($dashboard['movimentacoes_recentes'] as $mov)
                                @if ($mov['url'])
                                    <a href="{{ $mov['url'] }}" class="list-group-item list-group-item-action">
                                @else
                                    <div class="list-group-item">
                                @endif
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">{{ $mov['tipo'] }}</div>
                                                <small class="text-muted">{{ $mov['fruta'] }}</small>
                                            </div>
                                            <small class="text-muted text-nowrap">{{ $mov['data'] }}</small>
                                        </div>
                                @if ($mov['url'])
                                    </a>
                                @else
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0" role="note">
        <i class="ri-information-line me-1"></i>
        Versão inicial do painel: totais de cadastros e movimentações no seu escopo de unidades.
        Indicadores financeiros e gráficos serão adicionados em uma próxima etapa.
    </div>
@endsection
