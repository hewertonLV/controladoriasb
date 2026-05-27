<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\VendaNota;
use App\Support\Movimentacoes\VendaCustoOperacionalHub;
use Illuminate\Support\Facades\DB;

final class CorrigirCustosVendaSaidaHubService
{
    public function __construct(
        private readonly VendaMovimentacaoService $vendas,
        private readonly ReplayLinhaTempoEstoqueService $replay,
    ) {}

    public function corrigirNota(?string $numeroNf = null, bool $dryRun = false): int
    {
        $corrigidas = 0;

        DB::transaction(function () use ($numeroNf, $dryRun, &$corrigidas): void {
            $query = Movimentacao::query()
                ->with(['unidadeEstoque', 'unidadeFaturamento', 'vendaNota'])
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->whereNotNull('id_unidade_negocio_estoque');

            if ($numeroNf !== null && $numeroNf !== '') {
                $notaId = VendaNota::query()->where('numero_nf', $numeroNf)->value('id');
                if ($notaId === null) {
                    return;
                }
                $query->where('venda_nota_id', $notaId);
            }

            $reprocessos = [];

            foreach ($query->orderBy('id')->lockForUpdate()->get() as $venda) {
                if (! VendaCustoOperacionalHub::coEmbutidoNoCustoSaida($venda)) {
                    continue;
                }

                $custos = $this->vendas->custosHubParaMovimentacao($venda);
                if ($custos === null) {
                    continue;
                }

                $esperado = number_format($custos['valor_custo_saida'], 2, '.', '');
                $precisaCorrigir = VendaCustoOperacionalHub::divergeCustosHub($venda)
                    || (int) ($venda->id_custo_operacional ?? 0) !== (int) ($custos['id_custo_operacional'] ?? 0)
                    || (string) $venda->valor_custo_operacional !== (string) $custos['valor_custo_operacional'];

                if (! $precisaCorrigir) {
                    continue;
                }

                if (! $dryRun) {
                    $this->vendas->aplicarCustosHubCorrigidos($venda);
                    $reprocessos[(int) $venda->id_unidade_negocio_estoque.':'.(int) $venda->id_fruta] = [
                        'id_unidade_negocio' => (int) $venda->id_unidade_negocio_estoque,
                        'id_fruta' => (int) $venda->id_fruta,
                    ];
                }

                $corrigidas++;
            }

            if (! $dryRun) {
                foreach ($reprocessos as $reprocesso) {
                    $this->replay->reprocessarUnidadeFruta(
                        $reprocesso['id_unidade_negocio'],
                        $reprocesso['id_fruta'],
                    );
                }
            }
        });

        return $corrigidas;
    }
}
