<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GerarVendasCaptacaoLoteService
{
    public function __construct(
        private readonly VendaMovimentacaoService $vendas,
    ) {}

    /**
     * @return list<int> ids de VendaNota
     */
    public function executar(CaptacaoLote $lote, ?User $user = null): array
    {
        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Romaneio manual não gera vendas.',
            ]);
        }

        if (CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->exists()) {
            return CaptacaoLoteMovimentacao::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
                ->pluck('venda_nota_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $lote->load(['pedidos.itens.fruta', 'pedidos.cliente']);

        $faturamento = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_faturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaFaturamento = $faturamento->registroCorporativo()->firstOrFail();

        $fretesPorFruta = CaptacaoLoteFreteLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->pluck('id_frete', 'id_fruta');

        $notaIds = [];

        DB::transaction(function () use ($lote, $user, $faturamento, $galpao, $empresaFaturamento, $fretesPorFruta, &$notaIds): void {
            foreach ($lote->pedidos as $pedido) {
                if ($pedido->itens->isEmpty()) {
                    continue;
                }

                $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
                $empresaCliente = $cliente->registroCorporativo()->firstOrFail();

                $itens = [];
                foreach ($pedido->itens as $item) {
                    $qtdUm = (float) $item->quantidade;
                    if ($qtdUm <= 0) {
                        continue;
                    }
                    $precoUm = (float) ($item->preco_venda ?? 0);
                    $itens[] = [
                        'id_fruta' => $item->id_fruta,
                        'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                        'valor_nf_total' => number_format(round($precoUm * $qtdUm, 2), 2, '.', ''),
                    ];
                }

                if ($itens === []) {
                    continue;
                }

                $dataEmissao = $pedido->data_entrega
                    ?? $lote->data_referencia->copy()->addDay();

                $primeiraFruta = (int) $pedido->itens->first()->id_fruta;
                $idFrete = $fretesPorFruta->get($primeiraFruta);

                $resultado = $this->vendas->registrarVenda([
                    'numero_nf' => $this->numeroNfCaptacao($lote, $pedido->id_cliente),
                    'id_empresa_origem' => $empresaFaturamento->id,
                    'id_empresa_destino' => $empresaCliente->id,
                    'id_unidade_negocio_centro_resultado' => $galpao->id,
                    'id_unidade_negocio_estoque' => $galpao->id,
                    'data_emissao' => $dataEmissao->format('Y-m-d'),
                    'id_frete' => $idFrete,
                    'observacao' => "Captação lote #{$lote->id} pedido cliente #{$pedido->id_cliente}",
                    'itens' => $itens,
                ], $user);

                /** @var VendaNota $nota */
                $nota = $resultado['nota'];
                $notaIds[] = $nota->id;

                CaptacaoLoteMovimentacao::query()->create([
                    'id_captacao_lote' => $lote->id,
                    'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
                    'venda_nota_id' => $nota->id,
                ]);
            }
        });

        if ($notaIds === [] && $lote->pedidos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'vendas' => 'Nenhuma venda foi gerada. Verifique itens com quantidade e preço.',
            ]);
        }

        return $notaIds;
    }

    private function numeroNfCaptacao(CaptacaoLote $lote, int $idCliente): string
    {
        return sprintf(
            'CAP-%s-%d-%d',
            $lote->data_referencia->format('Ymd'),
            $lote->id,
            $idCliente,
        );
    }
}
