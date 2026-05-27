<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Exceptions\Captacao\CaptacaoEdicaoBloqueadaException;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ExcluirCaptacaoLoteService
{
    public function excluir(CaptacaoLote $lote): void
    {
        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            throw new CaptacaoEdicaoBloqueadaException(
                'Só é possível excluir captações com status «Captação em andamento».',
            );
        }

        if ($lote->movimentacoesVinculo()->exists()) {
            throw new CaptacaoEdicaoBloqueadaException(
                'Esta captação possui movimentações vinculadas e não pode ser excluída.',
            );
        }

        DB::transaction(function () use ($lote): void {
            $this->removerArquivos($lote);
            $this->removerPedidos($lote);

            $lote->freteLinhas()->delete();
            $lote->ciganExports()->delete();
            $lote->manualLinhas()->delete();
            $lote->movimentacoesVinculo()->delete();
            $lote->delete();
        });
    }

    private function removerPedidos(CaptacaoLote $lote): void
    {
        $pedidos = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->with('itens:id,id_pedido')
            ->get();

        foreach ($pedidos as $pedido) {
            $itemIds = $pedido->itens->pluck('id');

            if ($itemIds->isNotEmpty()) {
                DB::table('pedido_item_historicos')
                    ->whereIn('id_pedido_item', $itemIds)
                    ->delete();
            }

            DB::table('pedido_historicos')
                ->where('id_pedido', $pedido->id)
                ->delete();

            $pedido->itens()->delete();
            $pedido->forceDelete();
        }
    }

    private function removerArquivos(CaptacaoLote $lote): void
    {
        $disk = Storage::disk('local');

        if ($lote->possuiNfTransferencia()) {
            $disk->delete($lote->arquivo_nf_transferencia_path);
        }

        if ($lote->possuiNfVenda()) {
            $disk->delete($lote->arquivo_nf_venda_path);
        }

        foreach ($lote->ciganExports()->get(['caminho_arquivo']) as $export) {
            if ($export->caminho_arquivo !== null && $export->caminho_arquivo !== '') {
                $disk->delete($export->caminho_arquivo);
            }
        }
    }
}
