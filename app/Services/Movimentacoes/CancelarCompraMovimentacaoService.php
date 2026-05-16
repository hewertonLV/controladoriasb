<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEstoqueDestinoCompra;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\CategoriaMovimentacao;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelarCompraMovimentacaoService
{
    private const CATEGORIA_COMPRA_NOME = 'COMPRA';

    public function __construct(
        private readonly CompraMovimentacaoService $compraMovimentacao,
        private readonly ReprocessaEstoqueDestinoCompra $reprocessaEstoqueDestinoCompra,
        private readonly ReplayLinhaTempoEstoqueService $replayLinhaTempoEstoque,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo): void {
            $mov = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();

            $categoriaCompraId = CategoriaMovimentacao::idPorNome(self::CATEGORIA_COMPRA_NOME);
            if ((int) $mov->categoria_movimentacao_id !== $categoriaCompraId) {
                throw new InvalidArgumentException('Somente movimentações da categoria COMPRA podem ser canceladas por este fluxo.');
            }

            if ($mov->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente movimentações ativas podem ser canceladas administrativamente.');
            }

            $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $mov->id_empresa_destino);
            $unidade = $empresaDestino->entidade;
            if (! $unidade instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Destino inválido para compra.');
            }

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $mov->id_fruta)
                ->lockForUpdate()
                ->firstOrFail();

            if ($mov->id_frete !== null) {
                $frete = Frete::query()->whereKey((int) $mov->id_frete)->lockForUpdate()->first();
                if ($frete === null) {
                    throw new InvalidArgumentException('Frete vinculado não encontrado.');
                }
            }

            $dadosMovAntes = $this->auditoria->snapshotVersao($mov);
            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);

            $agora = now();

            $mov->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => $agora,
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            if ($mov->id_frete !== null) {
                $this->compraMovimentacao->recalcularRateioFreteParaTodasMovimentacoes((int) $mov->id_frete);
            }

            $this->reprocessaEstoqueDestinoCompra->reprocessarEstoqueDestinoUnidadeFruta($unidade->id, (int) $mov->id_fruta);
            $this->replayLinhaTempoEstoque->reprocessarUnidadeFruta($unidade->id, (int) $mov->id_fruta);

            $mov = $mov->fresh();
            if ($mov === null) {
                throw new InvalidArgumentException('Movimentação não encontrada após cancelamento.');
            }

            $estoqueDepois = $this->auditoria->snapshotEstoque(
                Estoque::query()->whereKey($estoque->id)->firstOrFail(),
            );

            $dadosMovDepois = $this->auditoria->snapshotVersao($mov);

            $this->auditoria->registrarCancelamentoAdministrativo(
                $mov,
                $user,
                $motivo,
                $dadosMovAntes,
                $dadosMovDepois,
                $estoqueAntes,
                $estoqueDepois,
                [
                    'categoria' => 'COMPRA',
                    'quantidade_fruta_kg_afetada' => (string) $movimentacao->qtd_fruta_kg,
                    'quantidade_fruta_um_afetada' => (string) $movimentacao->qtd_fruta_um,
                ],
            );
        });
    }
}
