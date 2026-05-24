<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoFaturamentoDiaStatus;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoFaturamentoDia;
use App\Models\Captacao\CaptacaoLote;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CaptacaoLoteService
{
    public function abrirOuRecuperarLote(
        string $dataReferencia,
        int $idUnidadeFaturamento,
        int $idUnidadeGalpao,
        CaptacaoLoteTipo $tipo = CaptacaoLoteTipo::CaptacaoPedidos,
    ): CaptacaoLote {
        $this->validarUnidades($idUnidadeFaturamento, $idUnidadeGalpao);

        $this->garantirFaturamentoDiaAberto($dataReferencia, $idUnidadeFaturamento);

        return CaptacaoLote::query()->firstOrCreate(
            [
                'data_referencia' => $dataReferencia,
                'id_unidade_negocio_galpao' => $idUnidadeGalpao,
            ],
            [
                'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
                'tipo' => $tipo,
                'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
            ],
        );
    }

    public function criarRomaneioManual(
        string $dataReferencia,
        int $idUnidadeFaturamento,
        int $idUnidadeGalpao,
    ): CaptacaoLote {
        return $this->abrirOuRecuperarLote(
            $dataReferencia,
            $idUnidadeFaturamento,
            $idUnidadeGalpao,
            CaptacaoLoteTipo::RomaneioManual,
        );
    }

    public function transicionarStatus(CaptacaoLote $lote, CaptacaoLoteStatus $novoStatus): CaptacaoLote
    {
        $lote->status = $novoStatus;
        $lote->save();

        return $lote->refresh();
    }

    private function garantirFaturamentoDiaAberto(string $dataReferencia, int $idUnidadeFaturamento): CaptacaoFaturamentoDia
    {
        $dia = CaptacaoFaturamentoDia::query()->firstOrCreate(
            [
                'data_referencia' => $dataReferencia,
                'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
            ],
            [
                'status' => CaptacaoFaturamentoDiaStatus::CaptacaoAberta,
            ],
        );

        if ($dia->status === CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada) {
            throw ValidationException::withMessages([
                'data_referencia' => 'A captação deste faturamento já foi finalizada para esta data.',
            ]);
        }

        return $dia;
    }

    private function validarUnidades(int $idUnidadeFaturamento, int $idUnidadeGalpao): void
    {
        $faturamento = UnidadeNegocio::query()->findOrFail($idUnidadeFaturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($idUnidadeGalpao);

        if ($galpao->is_galpao_operacional !== true) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_galpao' => 'A unidade selecionada não é um galpão operacional.',
            ]);
        }

        if ($faturamento->emite_nota_fiscal !== true) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_faturamento' => 'A unidade de faturamento deve emitir nota fiscal.',
            ]);
        }
    }
}
