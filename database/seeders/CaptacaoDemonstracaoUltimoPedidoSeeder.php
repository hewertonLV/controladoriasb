<?php

namespace Database\Seeders;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Enums\PedidoOrigem;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dados de demonstração: lote de captação de ontem com pedido da loja 22
 * para visualizar «Última captação» em pedidos-por-loja.
 *
 * Uso: php artisan db:seed --class=CaptacaoDemonstracaoUltimoPedidoSeeder
 */
class CaptacaoDemonstracaoUltimoPedidoSeeder extends Seeder
{
    public function run(): void
    {
        $loteReferencia = CaptacaoLote::query()->find(3);
        if ($loteReferencia === null) {
            $this->command?->warn('Lote 3 não encontrado; seeder ignorado.');

            return;
        }

        $cliente = Cliente::query()->find(22);
        if ($cliente === null) {
            $this->command?->warn('Cliente 22 não encontrado; seeder ignorado.');

            return;
        }

        $dataOntem = Carbon::parse($loteReferencia->data_referencia)->subDay()->toDateString();

        $loteOntem = CaptacaoLote::query()->firstOrCreate(
            [
                'data_referencia' => $dataOntem,
                'id_captacao_carteira' => $loteReferencia->id_captacao_carteira,
                'tipo' => CaptacaoLoteTipo::CaptacaoPedidos->value,
            ],
            [
                'id_unidade_negocio_faturamento' => $loteReferencia->id_unidade_negocio_faturamento,
                'id_unidade_negocio_galpao' => $loteReferencia->id_unidade_negocio_galpao,
                'status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan->value,
            ],
        );

        $idFrutas = ClienteFrutaVinculo::query()
            ->where('id_cliente', $cliente->id)
            ->where('ativo', true)
            ->orderBy('id_fruta')
            ->limit(8)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($idFrutas === []) {
            $this->command?->warn('Cliente 22 sem frutas vinculadas; seeder ignorado.');

            return;
        }

        $pedido = Pedido::query()->firstOrCreate(
            [
                'id_captacao_lote' => $loteOntem->id,
                'id_cliente' => $cliente->id,
            ],
            [
                'origem' => PedidoOrigem::Web,
                'captacao_concluida' => true,
            ],
        );

        $quantidades = ['12', '8', '15', '6', '20', '10', '5', '18'];
        $precos = ['45.50', '38.00', '52.90', '29.99', '41.20', '33.75', '48.00', '36.40'];
        $custos = ['32.00', '28.50', '38.00', '22.00', '30.00', '25.00', '35.00', '27.50'];

        foreach ($idFrutas as $i => $idFruta) {
            PedidoItem::query()->updateOrCreate(
                [
                    'id_pedido' => $pedido->id,
                    'id_fruta' => $idFruta,
                ],
                [
                    'quantidade' => $quantidades[$i] ?? '10',
                    'preco_venda' => $precos[$i] ?? '40.00',
                    'custo_referencia' => $custos[$i] ?? '28.00',
                    'version' => 1,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Demonstração criada: lote %d (%s), pedido %d, %d itens para cliente %d.',
            $loteOntem->id,
            $dataOntem,
            $pedido->id,
            count($idFrutas),
            $cliente->id,
        ));
    }
}
