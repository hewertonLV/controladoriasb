<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\EfetivarVendasCaptacaoLoteService;
use Illuminate\Http\UploadedFile;

final class EnviarNfVendaCiganLoteAction
{
    public function __construct(
        private readonly EfetivarVendasCaptacaoLoteService $efetivarVendas,
    ) {}

    public function executar(CaptacaoLote $lote, UploadedFile $arquivo, User $user): CaptacaoLote
    {
        return $this->efetivarVendas->executar($lote, $arquivo, $user);
    }
}
