<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use Illuminate\Validation\ValidationException;

final class AvancarEtapaFreteVendaCaptacaoLoteService
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
    ) {}

    /**
     * @throws ValidationException
     */
    public function concluirManualmente(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::VincularFreteVenda) {
            throw ValidationException::withMessages([
                'status' => 'A conclusão do frete de vendas só é permitida nesta etapa do lote.',
            ]);
        }

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::VendasFinalizadas);
    }
}
