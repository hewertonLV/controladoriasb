<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use Illuminate\Validation\ValidationException;

final class ConcluirCaptacaoLoteService
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly CaptacaoMatrizRotasService $matrizRotas,
        private readonly PedidoCaptacaoEstadoService $estadoCaptacao,
    ) {}

    /**
     * @return array{pode: bool, pendencias: list<string>}
     */
    public function pendenciasParaConcluir(CaptacaoLote $lote): array
    {
        $pendencias = [];

        if ($lote->tipo !== CaptacaoLoteTipo::CaptacaoPedidos) {
            $pendencias[] = 'Este lote não é de captação por pedidos.';
        }

        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            $pendencias[] = 'A captação deste lote não está em andamento.';
        }

        $rotasPendentes = $this->matrizRotas->nomesRotasComPedidoNaoConcluidasNoLote($lote);
        if ($rotasPendentes !== []) {
            $pendencias[] = 'Conclua todas as rotas na matriz (aba Por rota): '
                .implode(', ', $rotasPendentes).'.';
        }

        $lojasPendentes = $this->estadoCaptacao->lojasComPedidoNaoConcluido($lote)
            ->map(fn ($c) => $c->fantasia ?: $c->razao_social)
            ->take(5)
            ->all();

        if ($lojasPendentes !== []) {
            $pendencias[] = 'Finalize a captação de todas as lojas com quantidade. Pendentes: '
                .implode(', ', $lojasPendentes).'.';
        }

        return [
            'pode' => $pendencias === [],
            'pendencias' => $pendencias,
        ];
    }

    public function concluir(CaptacaoLote $lote): CaptacaoLote
    {
        $validacao = $this->pendenciasParaConcluir($lote);
        if (! $validacao['pode']) {
            throw ValidationException::withMessages([
                'lote' => $validacao['pendencias'],
            ]);
        }

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::CaptacaoConcluida);
    }
}
