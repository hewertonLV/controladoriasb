<?php

namespace App\Services\Movimentacoes;

use App\Enums\TransferenciaDemandaStatus;
use App\Models\Fruta;
use App\Models\Movimentacoes\TransferenciaDemanda;
use App\Models\Movimentacoes\TransferenciaDemandaLinha;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoDemandaEstoqueService;
use App\Services\Captacao\CaptacaoDemandaTransferenciaCigamGerador;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TransferenciaDemandaManualService
{
    public function __construct(
        private readonly CaptacaoDemandaEstoqueService $estoque,
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    /**
     * @param  array{
     *     id_unidade_negocio_origem: int,
     *     id_unidade_negocio_destino: int,
     *     observacao?: string|null,
     *     linhas: list<array{id_fruta: int, qtd_um: float|string}>,
     * }  $dados
     */
    public function salvar(array $dados, ?TransferenciaDemanda $demanda = null): TransferenciaDemanda
    {
        $this->assertUnidadesValidas(
            (int) $dados['id_unidade_negocio_origem'],
            (int) $dados['id_unidade_negocio_destino'],
        );

        return DB::transaction(function () use ($dados, $demanda): TransferenciaDemanda {
            if ($demanda === null) {
                $demanda = TransferenciaDemanda::query()->create([
                    'origem' => TransferenciaDemanda::ORIGEM_MANUAL,
                    'status' => TransferenciaDemandaStatus::DemandaCriada->value,
                    'id_unidade_negocio_origem' => (int) $dados['id_unidade_negocio_origem'],
                    'id_unidade_negocio_destino' => (int) $dados['id_unidade_negocio_destino'],
                    'observacao' => $dados['observacao'] ?? 'Demanda criada manualmente',
                ]);
            } else {
                $this->assertEditavel($demanda);
                $demanda->update([
                    'id_unidade_negocio_origem' => (int) $dados['id_unidade_negocio_origem'],
                    'id_unidade_negocio_destino' => (int) $dados['id_unidade_negocio_destino'],
                    'observacao' => $dados['observacao'] ?? $demanda->observacao,
                ]);
                $demanda->linhas()->delete();
            }

            foreach ($dados['linhas'] as $linha) {
                $qtd = round((float) $linha['qtd_um'], 3);
                if ($qtd <= 0) {
                    continue;
                }
                TransferenciaDemandaLinha::query()->create([
                    'id_transferencia_demanda' => $demanda->id,
                    'id_fruta' => (int) $linha['id_fruta'],
                    'qtd_um' => $qtd,
                ]);
            }

            return $demanda->fresh(['linhas.fruta', 'unidadeOrigem', 'unidadeDestino']);
        });
    }

    public function iniciar(TransferenciaDemanda $demanda): TransferenciaDemanda
    {
        $this->assertEditavel($demanda);
        $demanda->loadMissing('linhas');

        if ($demanda->linhas->isEmpty()) {
            throw ValidationException::withMessages(['linhas' => 'Informe ao menos uma fruta com quantidade.']);
        }

        $linhasValidacao = $demanda->linhas->map(fn ($l) => [
            'id_fruta' => (int) $l->id_fruta,
            'qtd_um' => (float) $l->qtd_um,
        ])->all();

        $validacao = $this->estoque->validarLinhas((int) $demanda->id_unidade_negocio_origem, $linhasValidacao);
        if (! $validacao['pode']) {
            throw ValidationException::withMessages([
                'estoque' => array_map(
                    fn (array $l): string => "{$l['fruta_nome']}: faltam {$l['qtd_falta']}",
                    array_filter($validacao['linhas'], static fn (array $x): bool => ! $x['ok']),
                ),
                'linhas' => $validacao['linhas'],
            ]);
        }

        $demanda->update(['status' => TransferenciaDemandaStatus::Iniciado->value]);

        return $demanda->fresh(['linhas.fruta']);
    }

    public function anexarNf(TransferenciaDemanda $demanda, UploadedFile $arquivo): TransferenciaDemanda
    {
        if ($demanda->status !== TransferenciaDemandaStatus::Iniciado->value) {
            throw ValidationException::withMessages(['status' => 'Anexe NF somente com demanda iniciada.']);
        }

        if ($demanda->nf_transferencia_path !== null) {
            Storage::disk('local')->delete($demanda->nf_transferencia_path);
        }

        $ext = strtolower($arquivo->getClientOriginalExtension() ?: 'bin');
        $path = sprintf('transferencias/demandas/nf-%d-%s.%s', $demanda->id, now()->format('YmdHis'), $ext);
        Storage::disk('local')->putFileAs(dirname($path), $arquivo, basename($path));

        $demanda->update([
            'nf_transferencia_path' => $path,
            'status' => TransferenciaDemandaStatus::VincularFrete->value,
        ]);

        return $demanda->fresh();
    }

    public function concluirComFrete(TransferenciaDemanda $demanda, ?int $idFrete): TransferenciaDemanda
    {
        if ($demanda->status !== TransferenciaDemandaStatus::VincularFrete->value) {
            throw ValidationException::withMessages(['status' => 'Demanda não está na etapa de vincular frete.']);
        }

        return DB::transaction(function () use ($demanda, $idFrete): TransferenciaDemanda {
            $demanda->loadMissing(['linhas.fruta', 'unidadeOrigem', 'unidadeDestino']);
            $origem = $demanda->unidadeOrigem ?? UnidadeNegocio::query()->findOrFail($demanda->id_unidade_negocio_origem);
            $destino = $demanda->unidadeDestino ?? UnidadeNegocio::query()->findOrFail($demanda->id_unidade_negocio_destino);
            $empresaOrigem = $origem->registroCorporativo()->firstOrFail();
            $empresaDestino = $destino->registroCorporativo()->firstOrFail();

            foreach ($demanda->linhas as $linha) {
                $par = $this->transferencias->criarTransferenciaAguardandoRecebimento([
                    'id_empresa_origem' => $empresaOrigem->id,
                    'id_empresa_destino' => $empresaDestino->id,
                    'id_fruta' => (int) $linha->id_fruta,
                    'qtd_fruta_um' => number_format(round((float) $linha->qtd_um, 2), 2, '.', ''),
                    'numero_nf_origem' => sprintf('DEM-MAN-%d-F%d', $demanda->id, $linha->id_fruta),
                    'observacao' => $demanda->observacao ?? 'Demanda manual',
                    'id_frete' => $idFrete,
                ]);
            }

            $demanda->update([
                'id_frete' => $idFrete,
                'status' => TransferenciaDemandaStatus::Concluido->value,
            ]);

            return $demanda->fresh(['linhas.fruta']);
        });
    }

    public function excluir(TransferenciaDemanda $demanda): void
    {
        if ($demanda->status === TransferenciaDemandaStatus::Concluido->value) {
            throw ValidationException::withMessages(['demanda' => 'Demanda concluída não pode ser excluída.']);
        }

        if ($demanda->nf_transferencia_path !== null) {
            Storage::disk('local')->delete($demanda->nf_transferencia_path);
        }

        $demanda->delete();
    }

    private function assertEditavel(TransferenciaDemanda $demanda): void
    {
        if ($demanda->status !== TransferenciaDemandaStatus::DemandaCriada->value) {
            throw ValidationException::withMessages([
                'status' => 'Somente demandas em «Demanda criada» podem ser editadas ou iniciadas.',
            ]);
        }
    }

    private function assertUnidadesValidas(int $origem, int $destino): void
    {
        if ($origem === $destino) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_destino' => 'Origem e destino devem ser diferentes.',
            ]);
        }
    }
}
