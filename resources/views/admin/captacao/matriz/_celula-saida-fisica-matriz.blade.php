@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \App\Models\Cliente $cliente */
    /** @var \App\Models\Captacao\Pedido|null $pedidoLinha */
    /** @var list<array{id: int, label: string, label_curto: string, grupo: string}> $opcoesSaidaFisicaMatriz */
    /** @var \App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService $saidaFisicaLoja */
    /** @var bool $linhaConcluida */

    $pedidoParaSaida = $pedidoLinha ?? new \App\Models\Captacao\Pedido(['id_unidade_negocio_saida_venda' => null]);
    $idSaidaAtual = $saidaFisicaLoja->idSaidaEfetivaParaExibicao($pedidoParaSaida, $lote, $cliente);
@endphp

<div class="captacao-matriz-saida-opcoes" data-saida-fisica-loja="{{ $cliente->id }}">
    @foreach ($opcoesSaidaFisicaMatriz as $opcao)
        <label class="captacao-matriz-saida-opcao mb-0">
            <input type="radio"
                   class="form-check-input captacao-saida-fisica-radio"
                   name="saida_fisica_{{ $cliente->id }}"
                   value="{{ $opcao['id'] }}"
                   data-cliente="{{ $cliente->id }}"
                   data-url="{{ route('admin.captacao.lotes.pedidos.saida-fisica-venda', [$lote, $cliente]) }}"
                   @checked($idSaidaAtual === $opcao['id'])
                   @disabled($linhaConcluida)>
            <span title="{{ $opcao['label'] }}">{{ $opcao['label_curto'] }}</span>
        </label>
    @endforeach
</div>
