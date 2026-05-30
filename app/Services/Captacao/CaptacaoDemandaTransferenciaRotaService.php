<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoDemandaStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\CaptacaoRota;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CaptacaoDemandaTransferenciaRotaService
{
    public function __construct(
        private readonly CaptacaoDemandaEstoqueService $estoque,
        private readonly TransferenciaMovimentacaoService $transferencias,
        private readonly CaptacaoDemandaTransferenciaCigamGerador $cigamGerador,
    ) {}

    /**
     * @return array{pode: bool, linhas: list<array<string, mixed>>}
     */
    public function validarEstoqueIniciar(CaptacaoLoteMovimentacao $demanda): array
    {
        $this->assertDemandaTransferenciaEditavel($demanda);

        return $this->estoque->validarLinhas(
            (int) $demanda->id_unidade_negocio_origem,
            $this->linhasParaValidacao($demanda),
        );
    }

    public function iniciar(CaptacaoLoteMovimentacao $demanda): CaptacaoLoteMovimentacao
    {
        $validacao = $this->validarEstoqueIniciar($demanda);
        if (! $validacao['pode']) {
            $mensagens = array_map(
                fn (array $linha): string => "{$linha['fruta_nome']}: faltam {$linha['qtd_falta']} (disponível {$linha['qtd_disponivel']})",
                array_filter($validacao['linhas'], static fn (array $l): bool => ! $l['ok']),
            );

            throw ValidationException::withMessages([
                'estoque' => $mensagens,
                'linhas' => $validacao['linhas'],
            ]);
        }

        $demanda->update(['status_demanda' => CaptacaoDemandaStatus::Iniciado->value]);

        return $demanda->fresh(['linhas.fruta']);
    }

    public function gerarArquivoCigam(CaptacaoLoteMovimentacao $demanda): string
    {
        $this->assertDemandaTransferenciaIniciada($demanda);

        $lote = CaptacaoLote::query()->findOrFail($demanda->id_captacao_lote);

        return $this->cigamGerador->gerar($lote, $demanda);
    }

    public function anexarNfEConcluir(
        CaptacaoLoteMovimentacao $demanda,
        UploadedFile $arquivo,
    ): CaptacaoLoteMovimentacao {
        $this->assertDemandaTransferenciaIniciada($demanda);

        return DB::transaction(function () use ($demanda, $arquivo): CaptacaoLoteMovimentacao {
            if ($demanda->nf_transferencia_path !== null) {
                Storage::disk('local')->delete($demanda->nf_transferencia_path);
            }

            $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->extension() ?: 'bin');
            $path = sprintf(
                'captacao/demandas/nf-transferencia/demanda-%d-%s.%s',
                $demanda->id,
                now()->format('YmdHis'),
                $extensao,
            );

            Storage::disk('local')->putFileAs(dirname($path), $arquivo, basename($path));

            $demanda->nf_transferencia_path = $path;

            if ($this->deveCriarMovimentacaoSb($demanda) && $demanda->transferencia_origem_id === null) {
                $this->criarMovimentacaoTransferencia($demanda);
            }

            $demanda->status_demanda = CaptacaoDemandaStatus::Concluido->value;
            $demanda->save();

            return $demanda->fresh(['linhas.fruta']);
        });
    }

    /**
     * Remove movimentação SB criada indevidamente em demanda automática (somente CIGAM).
     */
    public function reverterMovimentacaoSbIndevida(CaptacaoLoteMovimentacao $demanda): CaptacaoLoteMovimentacao
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
            throw ValidationException::withMessages(['demanda' => 'Demanda inválida.']);
        }

        if ($demanda->id_captacao_rota === null) {
            throw ValidationException::withMessages([
                'demanda' => 'Somente demandas automáticas da rota (fiscal/CIGAM) podem usar esta reversão.',
            ]);
        }

        $transferenciaOrigemId = (int) ($demanda->transferencia_origem_id ?? 0);
        if ($transferenciaOrigemId <= 0) {
            return $demanda->fresh(['linhas.fruta']);
        }

        $this->transferencias->cancelarTransferenciaPendenteRecebimento(
            $transferenciaOrigemId,
            'Reversão: demanda automática de captação é fiscal somente no CIGAM (ADR-0168)',
        );

        $demanda->transferencia_origem_id = null;
        $demanda->save();

        return $demanda->fresh(['linhas.fruta']);
    }

    public function excluir(CaptacaoLoteMovimentacao $demanda): void
    {
        $status = CaptacaoDemandaStatus::tryFrom((string) $demanda->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        if ($status === CaptacaoDemandaStatus::Concluido) {
            throw ValidationException::withMessages([
                'demanda' => 'Demanda concluída não pode ser excluída.',
            ]);
        }

        if ($demanda->id_captacao_rota !== null) {
            throw ValidationException::withMessages([
                'demanda' => 'Demanda gerada automaticamente na captação não pode ser excluída manualmente. Reabra a rota na matriz se precisar remover.',
            ]);
        }

        if ($demanda->transferencia_origem_id !== null) {
            $this->transferencias->cancelarTransferenciaPendenteRecebimento(
                (int) $demanda->transferencia_origem_id,
                'Exclusão de demanda de transferência',
            );
        }

        if ($demanda->nf_transferencia_path !== null) {
            Storage::disk('local')->delete($demanda->nf_transferencia_path);
        }

        $demanda->delete();
    }

    private function deveCriarMovimentacaoSb(CaptacaoLoteMovimentacao $demanda): bool
    {
        return $demanda->id_captacao_rota === null;
    }

    private function criarMovimentacaoTransferencia(CaptacaoLoteMovimentacao $demanda): void
    {
        $demanda->loadMissing(['linhas.fruta']);

        if ($demanda->linhas->isEmpty()) {
            throw ValidationException::withMessages([
                'demanda' => 'Demanda sem linhas de fruta para transferir.',
            ]);
        }

        $lote = CaptacaoLote::query()->findOrFail($demanda->id_captacao_lote);
        $rota = CaptacaoRota::query()->findOrFail($demanda->id_captacao_rota);

        $origem = UnidadeNegocio::query()->findOrFail($demanda->id_unidade_negocio_origem);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaOrigem = $origem->registroCorporativo()->firstOrFail();
        $empresaGalpao = $galpao->registroCorporativo()->firstOrFail();

        $numeroReferencia = sprintf(
            'CAP-TR-%d-R%d',
            $lote->id,
            $rota->id,
        );

        $transferenciaOrigemId = null;

        foreach ($demanda->linhas as $linha) {
            $par = $this->transferencias->criarTransferenciaAguardandoRecebimento([
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaGalpao->id,
                'id_fruta' => (int) $linha->id_fruta,
                'qtd_fruta_um' => number_format(round((float) $linha->qtd_um, 2), 2, '.', ''),
                'numero_nf_origem' => $numeroReferencia,
                'observacao' => "Captação lote #{$lote->id} rota «{$rota->nome}» demanda #{$demanda->id}",
            ], $transferenciaOrigemId);

            if ($transferenciaOrigemId === null) {
                $transferenciaOrigemId = (int) $par['saida']->transferencia_origem_id;
            }
        }

        $demanda->transferencia_origem_id = $transferenciaOrigemId;
    }

    /**
     * @return list<array{id_fruta: int, qtd_um: float}>
     */
    private function linhasParaValidacao(CaptacaoLoteMovimentacao $demanda): array
    {
        $demanda->loadMissing('linhas');

        if ($demanda->linhas->isEmpty() && $demanda->id_fruta !== null) {
            return [[
                'id_fruta' => (int) $demanda->id_fruta,
                'qtd_um' => (float) $demanda->qtd_um,
            ]];
        }

        return $demanda->linhas
            ->map(fn ($linha) => [
                'id_fruta' => (int) $linha->id_fruta,
                'qtd_um' => (float) $linha->qtd_um,
            ])
            ->all();
    }

    private function assertDemandaTransferenciaEditavel(CaptacaoLoteMovimentacao $demanda): void
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
            throw ValidationException::withMessages(['demanda' => 'Demanda inválida.']);
        }

        $status = CaptacaoDemandaStatus::tryFrom((string) $demanda->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        if ($status !== CaptacaoDemandaStatus::Aberto) {
            throw ValidationException::withMessages([
                'demanda' => 'Somente demandas em Aberto podem ser iniciadas.',
            ]);
        }
    }

    private function assertDemandaTransferenciaIniciada(CaptacaoLoteMovimentacao $demanda): void
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
            throw ValidationException::withMessages(['demanda' => 'Demanda inválida.']);
        }

        $status = CaptacaoDemandaStatus::tryFrom((string) $demanda->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        if ($status !== CaptacaoDemandaStatus::Iniciado) {
            throw ValidationException::withMessages([
                'demanda' => 'Anexe a NF somente com a demanda em Iniciado.',
            ]);
        }
    }
}
