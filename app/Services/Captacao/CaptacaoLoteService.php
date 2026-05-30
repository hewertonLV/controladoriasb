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

        $this->resolverFaturamentoDia($dataReferencia, $idUnidadeFaturamento);

        $loteEmAndamento = $this->loteCaptacaoEmAndamento($dataReferencia, $idCaptacaoCarteira, $tipo);

        if ($loteEmAndamento !== null) {
            return $loteEmAndamento;
        }

        return CaptacaoLote::query()->create([
            'data_referencia' => $dataReferencia,
            'id_captacao_carteira' => $idCaptacaoCarteira,
            'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
            'id_unidade_negocio_galpao' => $idUnidadeGalpao,
            'tipo' => $tipo,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);
    }

    public function possuiCaptacaoEmAndamento(
        string $dataReferencia,
        int $idCaptacaoCarteira,
        CaptacaoLoteTipo $tipo = CaptacaoLoteTipo::CaptacaoPedidos,
    ): bool {
        return $this->loteCaptacaoEmAndamento($dataReferencia, $idCaptacaoCarteira, $tipo) !== null;
    }

    /** Já existe outro lote (qualquer status) no mesmo dia × carteira — útil para mensagem «complementar». */
    public function possuiOutroLoteNaCarteiraData(
        string $dataReferencia,
        int $idCaptacaoCarteira,
        CaptacaoLoteTipo $tipo = CaptacaoLoteTipo::CaptacaoPedidos,
    ): bool {
        return CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_captacao_carteira', $idCaptacaoCarteira)
            ->where('tipo', $tipo->value)
            ->exists();
    }

    private function loteCaptacaoEmAndamento(
        string $dataReferencia,
        int $idCaptacaoCarteira,
        CaptacaoLoteTipo $tipo,
    ): ?CaptacaoLote {
        return CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_captacao_carteira', $idCaptacaoCarteira)
            ->where('tipo', $tipo->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->first();
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
     * Corrige lote travado em andamento após o dia de faturamento finalizado ([ADR-0102]).
     * Não altera captação complementar nova ([ADR-0121]): outro lote da mesma carteira/data já saiu de «em andamento».
     */
    public function sincronizarStatusComFaturamentoFinalizado(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->tipo !== CaptacaoLoteTipo::CaptacaoPedidos
            || $lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento
            || ! $this->faturamentoDiaFinalizado($lote)
            || $this->eCaptacaoComplementarIntencional($lote)) {
            return $lote;
        }

        return $this->transicionarStatus($lote, CaptacaoLoteStatus::CaptacaoConcluida);
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

        $atualizados = 0;

        CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->get()
            ->each(function (CaptacaoLote $lote) use (&$atualizados): void {
                if ($this->eCaptacaoComplementarIntencional($lote)) {
                    return;
                }

                $lote->update(['status' => CaptacaoLoteStatus::CaptacaoConcluida->value]);
                $atualizados++;
            });

        return $atualizados;
    }

    /**
     * Novo lote em captação no mesmo dia × carteira enquanto outro lote já avançou no pipeline.
     */
    public function eCaptacaoComplementarIntencional(CaptacaoLote $lote): bool
    {
        if ($lote->id_captacao_carteira === null) {
            return false;
        }

        return CaptacaoLote::query()
            ->whereDate('data_referencia', $lote->data_referencia)
            ->where('id_captacao_carteira', $lote->id_captacao_carteira)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->whereKeyNot($lote->id)
            ->where('status', '!=', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->exists();
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
