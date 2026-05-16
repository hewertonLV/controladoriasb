<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
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

final class CancelarVendaMovimentacaoAdminService
{
    public function __construct(
        private readonly VendaMovimentacaoService $vendaMovimentacao,
        private readonly ReprocessaSaidasVendaOrigem $reprocessaSaidasVendaOrigem,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo): void {
            $mov = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();

            if ((int) $mov->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Venda->value) {
                throw new InvalidArgumentException('Somente vendas podem ser canceladas por este fluxo.');
            }

            if ((int) $mov->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
                throw new InvalidArgumentException('Somente saídas de venda podem ser canceladas administrativamente.');
            }

            if ($mov->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente versões ativas podem ser canceladas administrativamente.');
            }

            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $mov->id_empresa_origem);
            $unidadeOrigem = $empresaOrigem->entidade;
            if (! $unidadeOrigem instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Unidade de origem inválida.');
            }

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', (int) $mov->id_fruta)
                ->lockForUpdate()
                ->firstOrFail();

            $dadosMovAntes = $this->auditoria->snapshotVersao($mov);
            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
            $idFrete = $mov->id_frete !== null ? (int) $mov->id_frete : null;

            $this->vendaMovimentacao->estornarVendaNoEstoqueOrigem($mov);

            $mov->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            $this->reprocessaSaidasVendaOrigem->reprocessarSaidasVendaNaUnidadeOrigem((int) $unidadeOrigem->id, (int) $mov->id_fruta, $mov->id);
            if ($idFrete !== null) {
                $this->vendaMovimentacao->recalcularRateioFreteParaVendas($idFrete);
            }

            $mov = $mov->fresh();
            if ($mov === null) {
                throw new InvalidArgumentException('Movimentação não encontrada após cancelamento administrativo.');
            }

            $this->auditoria->registrarCancelamentoAdministrativo(
                $mov,
                $user,
                $motivo,
                $dadosMovAntes,
                $this->auditoria->snapshotVersao($mov),
                $estoqueAntes,
                $this->auditoria->snapshotEstoque(Estoque::query()->whereKey($estoque->id)->firstOrFail()),
                [
                    'categoria' => 'VENDA',
                    'quantidade_fruta_kg_afetada' => (string) $movimentacao->qtd_fruta_kg,
                    'quantidade_fruta_um_afetada' => (string) $movimentacao->qtd_fruta_um,
                ],
            );
        });
    }
}
