<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EfetivarVendasCaptacaoLoteService
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly GerarVendasCaptacaoLoteService $gerarVendas,
        private readonly PedidoService $pedidos,
        private readonly ArmazenarNfVendaCiganLoteService $armazenarNf,
        private readonly AvancarEtapaVinculoRotasCaptacaoLoteService $avancarVinculoRotas,
    ) {}

    public function executar(CaptacaoLote $lote, UploadedFile $arquivoNf, User $user): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::FaturamentoCiganIniciado) {
            throw ValidationException::withMessages([
                'status' => 'O upload da NF de venda só é permitido com o faturamento Cigam iniciado.',
            ]);
        }

        $this->pedidos->assertSaidaFisicaVendaDefinidaParaLote($lote);

        return DB::transaction(function () use ($lote, $arquivoNf, $user): CaptacaoLote {
            $this->gerarVendas->executar($lote, $user);
            $this->armazenarNf->executar($lote, $arquivoNf, $user);

            $lote = $this->lotes->transicionarStatus(
                $lote->fresh(),
                CaptacaoLoteStatus::VincularRotasNosPedidos,
            );

            return $this->avancarVinculoRotas->tentarAvancarAutomaticamente($lote);
        });
    }
}
