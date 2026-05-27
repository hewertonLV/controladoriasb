<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\ArmazenarNfTransferenciaCiganLoteService;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\PedidoService;
use App\Services\Captacao\ValidarEstoqueHubNfTransferenciaCiganService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class EnviarNfTransferenciaCiganLoteAction
{
    public function __construct(
        private readonly ArmazenarNfTransferenciaCiganLoteService $armazenarNf,
        private readonly CaptacaoLoteService $lotes,
        private readonly PedidoService $pedidos,
        private readonly ValidarEstoqueHubNfTransferenciaCiganService $validarEstoqueHub,
    ) {}

    public function executar(CaptacaoLote $lote, UploadedFile $arquivo, User $user): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::TransferenciaCiganIniciada) {
            throw ValidationException::withMessages([
                'status' => 'O upload da NF de transferência só é permitido com a transferência Cigan iniciada.',
            ]);
        }

        $this->validarEstoqueHub->executar($lote);
        $this->armazenarNf->executar($lote, $arquivo, $user);
        $this->pedidos->garantirSaidaFisicaVendaPadraoGalpao($lote->fresh());

        return $this->lotes->transicionarStatus($lote->fresh(), CaptacaoLoteStatus::SaidaEstoqueFisico);
    }
}
