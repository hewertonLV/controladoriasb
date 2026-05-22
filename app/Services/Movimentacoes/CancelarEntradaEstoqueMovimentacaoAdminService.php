<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelarEntradaEstoqueMovimentacaoAdminService
{
    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $replayLinhaTempoEstoque,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo): void {
            $mov = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();

            if ((int) $mov->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::EntradaEstoque->value) {
                throw new InvalidArgumentException('Somente entradas de estoque podem ser canceladas por este fluxo.');
            }

            if ((int) $mov->status_movimentacao_id !== StatusMovimentacao::ID_ENTRADA) {
                throw new InvalidArgumentException('Somente lançamentos de entrada podem ser cancelados.');
            }

            if ($mov->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente versões ativas podem ser canceladas administrativamente.');
            }

            $empresa = Empresa::query()->with('entidade')->findOrFail((int) $mov->id_empresa_destino);
            $unidade = $empresa->entidade;
            if (! $unidade instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Unidade inválida.');
            }

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $mov->id_fruta)
                ->lockForUpdate()
                ->firstOrFail();

            $dadosMovAntes = $this->auditoria->snapshotVersao($mov);
            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);

            $mov->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            $this->replayLinhaTempoEstoque->reprocessarUnidadeFruta($unidade->id, (int) $mov->id_fruta);

            $mov = $mov->fresh();
            if ($mov === null) {
                throw new InvalidArgumentException('Movimentação não encontrada após cancelamento.');
            }

            $estoqueDepois = $this->auditoria->snapshotEstoque(
                Estoque::query()->whereKey($estoque->id)->firstOrFail(),
            );

            $this->auditoria->registrarCancelamentoAdministrativo(
                $mov,
                $user,
                $motivo,
                $dadosMovAntes,
                $this->auditoria->snapshotVersao($mov),
                $estoqueAntes,
                $estoqueDepois,
                [
                    'categoria' => 'ENTRADA_ESTOQUE',
                    'quantidade_fruta_kg_afetada' => (string) $movimentacao->qtd_fruta_kg,
                    'quantidade_fruta_um_afetada' => (string) $movimentacao->qtd_fruta_um,
                ],
            );
        });
    }
}
