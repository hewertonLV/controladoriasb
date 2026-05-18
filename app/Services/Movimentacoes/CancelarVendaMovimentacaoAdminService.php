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
        $this->cancelar($movimentacao, $user, $motivo, false);
    }

    public function executarItem(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        $this->cancelar($movimentacao, $user, $motivo, true);
    }

    private function cancelar(Movimentacao $movimentacao, User $user, string $motivo, bool $somenteItem): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo, $somenteItem): void {
            $ancora = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();

            $movimentacoes = Movimentacao::query()
                ->when(
                    $ancora->venda_nota_id !== null && ! $somenteItem,
                    fn ($query) => $query->where('venda_nota_id', $ancora->venda_nota_id),
                    fn ($query) => $query->whereKey($ancora->id),
                )
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($movimentacoes->isEmpty()) {
                throw new InvalidArgumentException('Nenhum item ativo encontrado para esta venda.');
            }

            $auditorias = [];
            $reprocessos = [];
            $fretes = [];

            foreach ($movimentacoes as $mov) {
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

                $auditorias[] = [
                    'movimentacao_id' => $mov->id,
                    'estoque_id' => $estoque->id,
                    'dados_mov_antes' => $this->auditoria->snapshotVersao($mov),
                    'estoque_antes' => $this->auditoria->snapshotEstoque($estoque),
                    'qtd_kg' => (string) $mov->qtd_fruta_kg,
                    'qtd_um' => (string) $mov->qtd_fruta_um,
                ];

                if ($mov->id_frete !== null) {
                    $fretes[(int) $mov->id_frete] = (int) $mov->id_frete;
                }

                $this->vendaMovimentacao->estornarVendaNoEstoqueOrigem($mov);

                $mov->forceFill([
                    'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                    'cancelada_por' => $user->id,
                    'cancelada_em' => now(),
                    'motivo_cancelamento' => $motivo,
                ])->saveQuietly();

                $reprocessos[$unidadeOrigem->id.':'.$mov->id_fruta] = [
                    'id_unidade_negocio' => (int) $unidadeOrigem->id,
                    'id_fruta' => (int) $mov->id_fruta,
                    'movimentacao_id' => (int) $mov->id,
                ];
            }

            if ($ancora->venda_nota_id !== null) {
                $itensAtivosRestantes = Movimentacao::query()
                    ->where('venda_nota_id', $ancora->venda_nota_id)
                    ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                    ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                    ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                    ->exists();

                $ancora->vendaNota()->update([
                    'status_registro' => $itensAtivosRestantes
                        ? MovimentacaoStatusRegistro::ATIVO->value
                        : MovimentacaoStatusRegistro::CANCELADO->value,
                ]);
            }

            foreach ($reprocessos as $reprocesso) {
                $this->reprocessaSaidasVendaOrigem->reprocessarSaidasVendaNaUnidadeOrigem(
                    $reprocesso['id_unidade_negocio'],
                    $reprocesso['id_fruta'],
                    $reprocesso['movimentacao_id'],
                );
            }

            foreach ($fretes as $idFrete) {
                $this->vendaMovimentacao->recalcularRateioFreteParaVendas($idFrete);
            }

            foreach ($auditorias as $auditoria) {
                $mov = Movimentacao::query()->findOrFail($auditoria['movimentacao_id']);
                $estoque = Estoque::query()->whereKey($auditoria['estoque_id'])->firstOrFail();

                $this->auditoria->registrarCancelamentoAdministrativo(
                    $mov,
                    $user,
                    $motivo,
                    $auditoria['dados_mov_antes'],
                    $this->auditoria->snapshotVersao($mov),
                    $auditoria['estoque_antes'],
                    $this->auditoria->snapshotEstoque($estoque),
                    [
                        'categoria' => 'VENDA',
                        'quantidade_fruta_kg_afetada' => $auditoria['qtd_kg'],
                        'quantidade_fruta_um_afetada' => $auditoria['qtd_um'],
                        'cancelamento_em_grupo' => ! $somenteItem && $movimentacoes->count() > 1,
                        'cancelamento_individual' => $somenteItem,
                        'venda_nota_id' => $ancora->venda_nota_id,
                    ],
                );
            }
        });
    }
}
