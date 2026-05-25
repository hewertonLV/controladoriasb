<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoFaturamentoDiaStatus;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoFaturamentoDia;
use App\Models\Captacao\CaptacaoLote;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CaptacaoLoteService
{
    public function __construct(
        private readonly CaptacaoCarteiraService $carteiras,
    ) {}

    public function abrirOuRecuperarLotePorCarteira(
        string $dataReferencia,
        int $idCaptacaoCarteira,
        CaptacaoLoteTipo $tipo = CaptacaoLoteTipo::CaptacaoPedidos,
    ): CaptacaoLote {
        $carteira = $this->carteiras->resolverPorId($idCaptacaoCarteira);

        return $this->abrirOuRecuperarLote(
            $dataReferencia,
            (int) $carteira->id_unidade_negocio_faturamento,
            (int) $carteira->id_unidade_negocio_galpao,
            $tipo,
            (int) $carteira->id,
        );
    }

    public function abrirOuRecuperarLote(
        string $dataReferencia,
        int $idUnidadeFaturamento,
        int $idUnidadeGalpao,
        CaptacaoLoteTipo $tipo = CaptacaoLoteTipo::CaptacaoPedidos,
        ?int $idCaptacaoCarteira = null,
    ): CaptacaoLote {
        $this->validarUnidades($idUnidadeFaturamento, $idUnidadeGalpao);

        $idCaptacaoCarteira ??= $this->garantirCarteira($idUnidadeFaturamento, $idUnidadeGalpao)->id;

        $this->garantirFaturamentoDiaAberto($dataReferencia, $idUnidadeFaturamento);

        return CaptacaoLote::query()->firstOrCreate(
            [
                'data_referencia' => $dataReferencia,
                'id_captacao_carteira' => $idCaptacaoCarteira,
            ],
            [
                'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
                'id_unidade_negocio_galpao' => $idUnidadeGalpao,
                'tipo' => $tipo,
                'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
            ],
        );
    }

    public function garantirCarteira(int $idUnidadeFaturamento, int $idUnidadeGalpao): CaptacaoCarteira
    {
        $this->validarUnidades($idUnidadeFaturamento, $idUnidadeGalpao);

        $existente = CaptacaoCarteira::query()
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('id_unidade_negocio_galpao', $idUnidadeGalpao)
            ->orderBy('id')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $nomeFat = UnidadeNegocio::query()->whereKey($idUnidadeFaturamento)->value('nome') ?? "UN {$idUnidadeFaturamento}";
        $nomeGalp = UnidadeNegocio::query()->whereKey($idUnidadeGalpao)->value('nome') ?? "Galpão {$idUnidadeGalpao}";

        return CaptacaoCarteira::query()->create([
            'nome' => mb_strtoupper("{$nomeFat} / {$nomeGalp}", 'UTF-8'),
            'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
            'id_unidade_negocio_galpao' => $idUnidadeGalpao,
            'ativo' => true,
        ]);
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

    public function faturamentoDiaFinalizado(CaptacaoLote $lote): bool
    {
        return CaptacaoFaturamentoDia::query()
            ->whereDate('data_referencia', $lote->data_referencia)
            ->where('id_unidade_negocio_faturamento', $lote->id_unidade_negocio_faturamento)
            ->where('status', CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada)
            ->exists();
    }

    /**
     * Corrige lote de captação que ficou em andamento após o faturamento/dia já ter sido finalizado.
     */
    public function sincronizarStatusComFaturamentoFinalizado(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->tipo !== CaptacaoLoteTipo::CaptacaoPedidos
            || $lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento
            || ! $this->faturamentoDiaFinalizado($lote)) {
            return $lote;
        }

        return $this->transicionarStatus($lote, CaptacaoLoteStatus::AguardandoTransferenciaCigan);
    }

    public function sincronizarLotesEmAndamentoQuandoDiaFinalizado(string $dataReferencia, int $idUnidadeFaturamento): int
    {
        $diaFinalizado = CaptacaoFaturamentoDia::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('status', CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada)
            ->exists();

        if (! $diaFinalizado) {
            return 0;
        }

        return CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan->value]);
    }

    public function resolverFaturamentoDia(string $dataReferencia, int $idUnidadeFaturamento): CaptacaoFaturamentoDia
    {
        $dia = CaptacaoFaturamentoDia::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->first();

        if ($dia !== null) {
            return $dia;
        }

        return CaptacaoFaturamentoDia::query()->create([
            'data_referencia' => $dataReferencia,
            'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
            'status' => CaptacaoFaturamentoDiaStatus::CaptacaoAberta,
        ]);
    }

    private function garantirFaturamentoDiaAberto(string $dataReferencia, int $idUnidadeFaturamento): CaptacaoFaturamentoDia
    {
        $dia = $this->resolverFaturamentoDia($dataReferencia, $idUnidadeFaturamento);

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
