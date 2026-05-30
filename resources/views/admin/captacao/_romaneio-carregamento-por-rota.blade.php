@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>> $romaneiosPorRota */
    /** @var \App\Models\Captacao\CaptacaoLote|null $lote */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\Pedido>|\Illuminate\Database\Eloquent\Collection|null $pedidosPorCliente */
    $lote ??= null;
    $pedidosPorCliente ??= null;
    $variante = $variante ?? 'simple';
    $idPrefixo = $idPrefixo ?? 'romaneio-rota';
@endphp

@include('admin.captacao._romaneio-carregamento-estilos')

@if ($romaneiosPorRota->isEmpty())
    <p class="text-muted mb-0">Nenhuma loja com rota vinculada e quantidade informada.</p>
@else
    <div class="captacao-romaneio-por-rota" data-id-prefixo="{{ $idPrefixo }}">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <ul class="nav nav-tabs captacao-romaneio-nav-tabs flex-grow-1 mb-0" role="tablist">
                @foreach ($romaneiosPorRota as $indice => $romaneioRota)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($indice === 0) active @endif"
                                id="{{ $idPrefixo }}-tab-{{ $romaneioRota['id_captacao_rota'] }}"
                                data-bs-toggle="tab"
                                data-bs-target="#{{ $idPrefixo }}-pane-{{ $romaneioRota['id_captacao_rota'] }}"
                                type="button"
                                role="tab">
                            {{ $romaneioRota['titulo_aba'] }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <button type="button"
                    class="btn btn-soft-secondary btn-sm captacao-romaneio-imprimir flex-shrink-0"
                    title="Imprimir a rota selecionada">
                <i class="ri-printer-line me-1" aria-hidden="true"></i>
                Imprimir rota
            </button>
        </div>

        <div class="tab-content">
            @foreach ($romaneiosPorRota as $indice => $romaneioRota)
                @php
                    $nomeCarro = '—';
                    if (! empty($romaneioRota['veiculo_rotulo'])) {
                        $nomeCarro = preg_replace('/\s*\(SBS\s+.+\)\s*$/u', '', (string) $romaneioRota['veiculo_rotulo']);
                        $nomeCarro = trim($nomeCarro) !== '' ? trim($nomeCarro) : '—';
                    }
                    $motoristaTitulo = trim((string) ($romaneioRota['motorista_nome'] ?? '')) !== ''
                        ? trim((string) $romaneioRota['motorista_nome'])
                        : '—';
                    $tituloImpressao = $romaneioRota['rota_nome'].' - '.$romaneioRota['carteira_nome'];
                    if ($lote !== null) {
                        $tituloImpressao .= ' , Captação Nº '.$lote->id.'  - Dia: '.$lote->data_referencia->format('d/m/Y');
                    }
                    $tituloImpressao .= ' - ('.$motoristaTitulo.' - '.$nomeCarro.')';
                @endphp
                <div class="tab-pane fade @if ($indice === 0) show active @endif"
                     id="{{ $idPrefixo }}-pane-{{ $romaneioRota['id_captacao_rota'] }}"
                     role="tabpanel"
                     aria-labelledby="{{ $idPrefixo }}-tab-{{ $romaneioRota['id_captacao_rota'] }}">
                    <div class="captacao-romaneio-print-cabecalho">
                        <h2 class="captacao-romaneio-print-titulo">{{ $tituloImpressao }}</h2>
                    </div>

                    @if (! empty($romaneioRota['motorista_nome']) || ! empty($romaneioRota['veiculo_rotulo']))
                        <div class="small text-muted mb-2 d-print-none">
                            @if (! empty($romaneioRota['motorista_nome']))
                                <span class="me-3">Motorista: <strong>{{ $romaneioRota['motorista_nome'] }}</strong></span>
                            @endif
                            @if (! empty($romaneioRota['veiculo_rotulo']))
                                <span>Veículo: <strong>{{ $romaneioRota['veiculo_rotulo'] }}</strong></span>
                            @endif
                        </div>
                    @endif

                    @if ($variante === 'saida-fisico' && $lote !== null && $pedidosPorCliente !== null)
                        @include('admin.captacao._romaneio-carregamento-saida-fisico', [
                            'lote' => $lote,
                            'romaneioCarregamento' => $romaneioRota['lojas'],
                            'romaneioCarregamentoTotaisGerais' => $romaneioRota['totais_gerais'],
                            'pedidosPorCliente' => $pedidosPorCliente,
                            'exibirRota' => false,
                        ])
                    @else
                        @include('admin.captacao._romaneio-carregamento-tabela', [
                            'romaneioCarregamento' => $romaneioRota['lojas'],
                            'romaneioCarregamentoTotaisGerais' => $romaneioRota['totais_gerais'],
                            'exibirRota' => false,
                        ])
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                (function () {
                    function iniciarImpressaoRomaneio(btn) {
                        const root = btn.closest('.captacao-romaneio-por-rota');
                        if (!root) {
                            return;
                        }

                        document.body.classList.add('captacao-romaneio-print-ativo');
                        root.classList.add('captacao-romaneio-printando');

                        const limpar = () => {
                            document.body.classList.remove('captacao-romaneio-print-ativo');
                            root.classList.remove('captacao-romaneio-printando');
                        };

                        window.addEventListener('afterprint', limpar, { once: true });
                        window.print();
                    }

                    document.addEventListener('click', (event) => {
                        const btn = event.target.closest('.captacao-romaneio-imprimir');
                        if (!btn) {
                            return;
                        }

                        event.preventDefault();
                        iniciarImpressaoRomaneio(btn);
                    });
                })();
            </script>
        @endpush
    @endonce
@endif
