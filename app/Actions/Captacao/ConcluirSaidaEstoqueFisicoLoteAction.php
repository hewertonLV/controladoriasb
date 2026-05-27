<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\EfetivarTransferenciasGerenciaisLoteService;
use App\Services\Captacao\PedidoService;
use App\Services\Captacao\ValidarEstoqueHubNfTransferenciaCiganService;
use Illuminate\Validation\ValidationException;

final class ConcluirSaidaEstoqueFisicoLoteAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly EfetivarTransferenciasGerenciaisLoteService $transferenciasGerenciais,
        private readonly PedidoService $pedidos,
        private readonly ValidarEstoqueHubNfTransferenciaCiganService $validarEstoqueHub,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::SaidaEstoqueFisico) {
            throw ValidationException::withMessages([
                'status' => 'O lote não está na etapa de saída estoque físico.',
            ]);
        }

        $this->pedidos->assertSaidaFisicaVendaDefinidaParaLote($lote);
        $this->validarEstoqueHub->executar($lote);
        $this->transferenciasGerenciais->executar($lote);
        $this->pedidos->sincronizarOrigemFisicaItensComSaidaVenda($lote);

        return $this->lotes->transicionarStatus($lote->fresh(), CaptacaoLoteStatus::AguardandoVinculoFrete);
    }
}
