@extends('layouts.app')

@section('title', 'Rentabilidade por loja')
@section('page-title', 'Relatórios — Rentabilidade por loja')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header">
            <h4 class="header-title mb-0">Filtros</h4>
            <p class="text-muted mb-0 small">
                Consolida vendas e devoluções por cliente (loja), com custo de saída da unidade de origem registrado na venda.
            </p>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.relatorios.rentabilidade-loja.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data inicial</label>
                    <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                           value="{{ $dados['filtros']['data_inicio'] }}" required>
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data final</label>
                    <input type="date" name="data_fim" id="data_fim" class="form-control"
                           value="{{ $dados['filtros']['data_fim'] }}" required>
                </div>
                <div class="col-md-3">
                    <label for="id_empresa_origem" class="form-label">Unidade de origem</label>
                    <select name="id_empresa_origem" id="id_empresa_origem" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($unidadesOrigem as $empresa)
                            <option value="{{ $empresa->id }}" @selected((int) ($dados['filtros']['id_empresa_origem'] ?? 0) === (int) $empresa->id)>
                                {{ $empresa->nomeExibicao() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_empresa_destino" class="form-label">Loja (cliente)</label>
                    <select name="id_empresa_destino" id="id_empresa_destino" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($clientes as $empresa)
                            <option value="{{ $empresa->id }}" @selected((int) ($dados['filtros']['id_empresa_destino'] ?? 0) === (int) $empresa->id)>
                                {{ $empresa->nomeExibicao() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="agrupamento" class="form-label">Agrupamento</label>
                    <select name="agrupamento" id="agrupamento" class="form-select">
                        <option value="cliente" @selected($dados['agrupamento'] === 'cliente')>Por loja (cliente)</option>
                        <option value="detalhe" @selected($dados['agrupamento'] === 'detalhe')>Detalhe (loja + origem + fruta)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-filter-3-line me-1"></i> Gerar relatório
                    </button>
                </div>
            </form>
        </div>
    </div>

    @php
        $t = $dados['totais'];
        $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtMargem = static function (?float $v): string {
            if ($v === null) {
                return '—';
            }

            return number_format($v, 2, ',', '.') . '%';
        };
        $clsResultado = static function (float $v): string {
            if ($v > 0) {
                return 'text-success';
            }
            if ($v < 0) {
                return 'text-danger';
            }

            return 'text-muted';
        };
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <div>
                <h4 class="header-title mb-0">Resultado</h4>
                <p class="text-muted mb-0 small">
                    Período: {{ \Illuminate\Support\Carbon::parse($dados['filtros']['data_inicio'])->format('d/m/Y') }}
                    a {{ \Illuminate\Support\Carbon::parse($dados['filtros']['data_fim'])->format('d/m/Y') }}
                </p>
            </div>
            <span class="badge bg-light text-dark ms-auto">
                {{ count($dados['linhas']) }} {{ count($dados['linhas']) === 1 ? 'linha' : 'linhas' }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>Loja (cliente)</th>
                            @if ($dados['agrupamento'] === 'detalhe')
                                <th>Unidade origem</th>
                                <th>Fruta</th>
                            @endif
                            <th class="text-end">Kg vendido</th>
                            <th class="text-end">Venda NF</th>
                            <th class="text-end">Custo saída</th>
                            <th class="text-end">Frete</th>
                            <th class="text-end">Res. vendas</th>
                            <th class="text-end">Kg devolvido</th>
                            <th class="text-end">Devolução NF</th>
                            <th class="text-end">Custo dev.</th>
                            <th class="text-end">Res. devoluções</th>
                            <th class="text-end">Custo médio/kg</th>
                            <th class="text-end">Resultado líquido</th>
                            <th class="text-end">Margem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dados['linhas'] as $linha)
                            <tr>
                                <td>{{ $linha['cliente_nome'] }}</td>
                                @if ($dados['agrupamento'] === 'detalhe')
                                    <td>{{ $linha['unidade_origem_nome'] }}</td>
                                    <td>{{ $linha['fruta_nome'] }}</td>
                                @endif
                                <td class="text-end">{{ $fmt($linha['venda_qtd_kg']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['venda_valor_nf']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['venda_custo_saida']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['venda_frete']) }}</td>
                                <td class="text-end {{ $clsResultado($linha['venda_resultado']) }}">R$ {{ $fmt($linha['venda_resultado']) }}</td>
                                <td class="text-end">{{ $fmt($linha['dev_qtd_kg']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['dev_valor_nf']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['dev_custo']) }}</td>
                                <td class="text-end {{ $clsResultado($linha['dev_resultado']) }}">R$ {{ $fmt($linha['dev_resultado']) }}</td>
                                <td class="text-end">R$ {{ $fmt($linha['custo_medio_kg']) }}</td>
                                <td class="text-end fw-semibold {{ $clsResultado($linha['resultado_liquido']) }}">R$ {{ $fmt($linha['resultado_liquido']) }}</td>
                                <td class="text-end">{{ $fmtMargem($linha['margem_percentual']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $dados['agrupamento'] === 'detalhe' ? 14 : 12 }}" class="text-center text-muted py-4">
                                    Nenhuma venda ou devolução no período com os filtros informados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($dados['linhas']) > 0)
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="{{ $dados['agrupamento'] === 'detalhe' ? 3 : 1 }}">Total</td>
                                <td class="text-end">{{ $fmt($t['venda_qtd_kg']) }}</td>
                                <td class="text-end">R$ {{ $fmt($t['venda_valor_nf']) }}</td>
                                <td class="text-end">R$ {{ $fmt($t['venda_custo_saida']) }}</td>
                                <td class="text-end">R$ {{ $fmt($t['venda_frete']) }}</td>
                                <td class="text-end {{ $clsResultado($t['venda_resultado']) }}">R$ {{ $fmt($t['venda_resultado']) }}</td>
                                <td class="text-end">{{ $fmt($t['dev_qtd_kg']) }}</td>
                                <td class="text-end">R$ {{ $fmt($t['dev_valor_nf']) }}</td>
                                <td class="text-end">R$ {{ $fmt($t['dev_custo']) }}</td>
                                <td class="text-end {{ $clsResultado($t['dev_resultado']) }}">R$ {{ $fmt($t['dev_resultado']) }}</td>
                                <td class="text-end">—</td>
                                <td class="text-end {{ $clsResultado($t['resultado_liquido']) }}">R$ {{ $fmt($t['resultado_liquido']) }}</td>
                                <td class="text-end">{{ $fmtMargem($t['margem_percentual']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-3 mb-0 small">
        <strong>Como ler:</strong>
        <em>Custo saída</em> é o custo médio da fruta na unidade de origem no momento de cada venda (<code>valor_custo_saida</code>).
        <em>Res. vendas</em> = valor da NF − custo − frete rateado.
        <em>Res. devoluções</em> usa o cálculo já gravado na devolução (com ou sem retorno ao estoque).
        <em>Resultado líquido</em> = resumo de vendas + devoluções no período.
    </div>
@endsection
