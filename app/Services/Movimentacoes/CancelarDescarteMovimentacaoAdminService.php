<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasDescarteOrigem;
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

final class CancelarDescarteMovimentacaoAdminService
{
    public function __construct(
        private readonly DescarteMovimentacaoService $descarteMovimentacao,
        private readonly ReprocessaSaidasDescarteOrigem $reprocessaSaidasDescarteOrigem,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo): void {
            $mov = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();

            if ((int) $mov->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Descarte->value) {
                throw new InvalidArgumentException('Somente descartes podem ser cancelados por este fluxo.');
            }

            if ((int) $mov->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
                throw new InvalidArgumentException('Somente saídas de descarte podem ser canceladas administrativamente.');
            }

            if ($mov->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente versões ativas podem ser canceladas administrativamente.');
            }

            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $mov->id_empresa_origem);
            $unidadeOrigem = $empresaOrigem->entidade;
            if (! $unidadeOrigem instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Unidade de origem inválida.');
            }

            $frutaId = (int) $mov->id_fruta;

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $frutaId)
                ->lockForUpdate()
                ->firstOrFail();

            $dadosMovAntes = $this->auditoria->snapshotVersao($mov);
            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);

            $this->descarteMovimentacao->estornarDescarteNoEstoqueOrigem($mov);

            $mov->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            $this->reprocessaSaidasDescarteOrigem->reprocessarSaidasDescarteNaUnidadeOrigem((int) $unidadeOrigem->id, $frutaId);

            $mov = $mov->fresh();
            if ($mov === null) {
                throw new InvalidArgumentException('Movimentação não encontrada após cancelamento administrativo.');
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
                    'categoria' => 'DESCARTE',
                    'quantidade_fruta_kg_afetada' => (string) $movimentacao->qtd_fruta_kg,
                    'quantidade_fruta_um_afetada' => (string) $movimentacao->qtd_fruta_um,
                ],
            );
        });
    }
}
