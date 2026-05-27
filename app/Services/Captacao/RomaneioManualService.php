<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Exceptions\Captacao\CaptacaoEdicaoBloqueadaException;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use Illuminate\Validation\ValidationException;

final class RomaneioManualService
{
    public function assertLoteRomaneioManual(CaptacaoLote $lote): void
    {
        if ($lote->tipo !== CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Este lote não é uma solicitação de transferência.',
            ]);
        }
    }

    public function assertPermiteEditarLinhas(CaptacaoLote $lote): void
    {
        $this->assertLoteRomaneioManual($lote);

        if (! $lote->status->permiteEdicaoQuantidadeCaptacao()) {
            throw new CaptacaoEdicaoBloqueadaException('A solicitação de transferência não está em edição.');
        }
    }

    public function adicionarFruta(
        CaptacaoLote $lote,
        int $idFruta,
        int $idUnidadeOrigemFisica,
        ?string $motivo = null,
    ): CaptacaoRomaneioManualLinha {
        $this->assertPermiteEditarLinhas($lote);

        $existente = CaptacaoRomaneioManualLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_fruta', $idFruta)
            ->where('id_unidade_origem_fisica', $idUnidadeOrigemFisica)
            ->first();

        if ($existente !== null) {
            throw ValidationException::withMessages([
                'id_fruta' => 'Esta fruta já está no romaneio com a mesma origem física.',
            ]);
        }

        return CaptacaoRomaneioManualLinha::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_fruta' => $idFruta,
            'quantidade' => 0,
            'id_unidade_origem_fisica' => $idUnidadeOrigemFisica,
            'motivo' => $motivo,
        ]);
    }

    /**
     * @param  array{quantidade?: mixed, incremento?: mixed, motivo?: string|null}  $dados
     */
    public function atualizarLinha(
        CaptacaoLote $lote,
        CaptacaoRomaneioManualLinha $linha,
        array $dados,
    ): CaptacaoRomaneioManualLinha {
        $this->assertPermiteEditarLinhas($lote);

        abort_unless($linha->id_captacao_lote === $lote->id, 404);

        $quantidade = $this->resolverQuantidade($linha, $dados);

        $linha->fill([
            'quantidade' => $quantidade,
            'motivo' => array_key_exists('motivo', $dados) ? $dados['motivo'] : $linha->motivo,
        ]);
        $linha->save();

        return $linha->refresh();
    }

    /**
     * @param  array{quantidade?: mixed, incremento?: mixed}  $dados
     */
    private function resolverQuantidade(CaptacaoRomaneioManualLinha $linha, array $dados): string
    {
        if (isset($dados['incremento']) && $dados['incremento'] !== null && $dados['incremento'] !== '') {
            $base = (float) $linha->quantidade;

            return number_format(max(0, $base + (float) $dados['incremento']), 3, '.', '');
        }

        return number_format((float) ($dados['quantidade'] ?? 0), 3, '.', '');
    }
}
