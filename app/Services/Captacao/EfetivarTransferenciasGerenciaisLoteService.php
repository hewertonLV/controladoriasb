<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EfetivarTransferenciasGerenciaisLoteService
{
    public function __construct(
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    /**
     * @return list<int> transferencia_origem_id criados
     */
    public function executar(CaptacaoLote $lote): array
    {
        if (CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->exists()) {
            return CaptacaoLoteMovimentacao::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
                ->pluck('transferencia_origem_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaGalpao = $galpao->registroCorporativo()->firstOrFail();

        $linhas = $this->romaneioAbastecimento->preview($lote);
        $criados = [];

        DB::transaction(function () use ($lote, $linhas, $empresaGalpao, &$criados): void {
            foreach ($linhas as $linha) {
                $aReceberKg = (float) $linha['a_receber_kg'];
                if ($aReceberKg <= 0) {
                    continue;
                }

                $fruta = Fruta::query()->findOrFail($linha['id_fruta']);
                $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
                if ($kgPorUm <= 0) {
                    continue;
                }

                $origemUn = $this->resolverUnidadeOrigem($lote, $fruta->id);
                $empresaOrigem = $origemUn->registroCorporativo()->firstOrFail();

                $qtdUm = round($aReceberKg / $kgPorUm, 2);
                if ($qtdUm <= 0) {
                    continue;
                }

                $par = $this->transferencias->criarTransferencia([
                    'id_empresa_origem' => $empresaOrigem->id,
                    'id_empresa_destino' => $empresaGalpao->id,
                    'id_fruta' => $fruta->id,
                    'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                ]);

                $transferenciaOrigemId = (int) $par['saida']->transferencia_origem_id;
                $criados[] = $transferenciaOrigemId;

                CaptacaoLoteMovimentacao::query()->create([
                    'id_captacao_lote' => $lote->id,
                    'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
                    'id_fruta' => $fruta->id,
                    'transferencia_origem_id' => $transferenciaOrigemId,
                ]);
            }
        });

        if ($criados === [] && $lote->tipo !== CaptacaoLoteTipo::RomaneioManual) {
            $temDemanda = $linhas->contains(fn (array $l): bool => (float) $l['a_receber_kg'] > 0);
            if ($temDemanda) {
                throw ValidationException::withMessages([
                    'transferencias' => 'Não foi possível gerar transferências. Verifique estoque na origem (HUB) e cadastro de empresas.',
                ]);
            }
        }

        return $criados;
    }

    private function resolverUnidadeOrigem(CaptacaoLote $lote, int $idFruta): UnidadeNegocio
    {
        $lote->loadMissing(['pedidos.itens']);

        $origens = collect();

        foreach ($lote->pedidos as $pedido) {
            foreach ($pedido->itens as $item) {
                if ($item->id_fruta !== $idFruta || $item->id_unidade_origem_fisica === null) {
                    continue;
                }
                $origens->push((int) $item->id_unidade_origem_fisica);
            }
        }

        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            $manual = CaptacaoRomaneioManualLinha::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('id_fruta', $idFruta)
                ->first();

            if ($manual !== null) {
                return UnidadeNegocio::query()->findOrFail($manual->id_unidade_origem_fisica);
            }
        }

        $origemId = $origens
            ->unique()
            ->map(fn (int $id) => UnidadeNegocio::query()->find($id))
            ->filter()
            ->sortByDesc(fn (?UnidadeNegocio $u) => $u?->is_hub ? 1 : 0)
            ->first();

        if ($origemId instanceof UnidadeNegocio) {
            return $origemId;
        }

        $hub = UnidadeNegocio::query()->where('is_hub', true)->where('possui_estoque', true)->first();
        if ($hub === null) {
            throw ValidationException::withMessages([
                'origem' => 'Nenhuma unidade HUB com estoque cadastrada para abastecer o galpão.',
            ]);
        }

        return $hub;
    }
}
