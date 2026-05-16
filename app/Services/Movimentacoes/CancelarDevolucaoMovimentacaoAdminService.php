<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEntradasDevolucaoDestino;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoDevolucao;
use App\Models\Estoque;
use App\Models\Movimentacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelarDevolucaoMovimentacaoAdminService
{
    public function __construct(
        private readonly DevolucaoMovimentacaoService $devolucao,
        private readonly ReprocessaEntradasDevolucaoDestino $replayDevolucao,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function executar(Movimentacao $movimentacao, User $user, string $motivo): void
    {
        DB::transaction(function () use ($movimentacao, $user, $motivo): void {
            $mov = Movimentacao::query()->with('vendaOrigem')->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            if ((int) $mov->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Devolucao->value) {
                throw new InvalidArgumentException('Somente devoluções podem ser canceladas por este fluxo.');
            }
            if ($mov->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
                throw new InvalidArgumentException('Somente versões ativas podem ser canceladas administrativamente.');
            }

            $dadosAntes = $this->auditoria->snapshotVersao($mov);
            $estoqueAntes = [];
            $estoqueDepois = [];

            if ($mov->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value && $mov->vendaOrigem !== null) {
                $unidade = $this->devolucao->unidadeDestinoEstoque($mov->vendaOrigem, $mov->id_unidade_negocio_retorno);
                $estoque = Estoque::query()
                    ->where('id_unidade_negocio', $unidade->id)
                    ->where('id_fruta', (int) $mov->id_fruta)
                    ->lockForUpdate()
                    ->firstOrFail();
                $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
            }

            $mov->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'cancelada_por' => $user->id,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ])->saveQuietly();

            if ($mov->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value && $mov->vendaOrigem !== null) {
                $unidade = $this->devolucao->unidadeDestinoEstoque($mov->vendaOrigem, $mov->id_unidade_negocio_retorno);
                $this->replayDevolucao->reprocessarEntradasDevolucaoNaUnidadeDestino($unidade->id, (int) $mov->id_fruta, $mov->id);
                $estoqueDepois = $this->auditoria->snapshotEstoque(Estoque::query()
                    ->where('id_unidade_negocio', $unidade->id)
                    ->where('id_fruta', (int) $mov->id_fruta)
                    ->firstOrFail());
            }

            $mov = $mov->fresh();
            $this->auditoria->registrarCancelamentoAdministrativo(
                $mov,
                $user,
                $motivo,
                $dadosAntes,
                $this->auditoria->snapshotVersao($mov),
                $estoqueAntes,
                $estoqueDepois,
                [
                    'categoria' => 'DEVOLUCAO',
                    'tipo_devolucao' => $mov->tipo_devolucao,
                    'movimentacao_venda_origem_id' => $mov->movimentacao_venda_origem_id,
                    'id_unidade_negocio_retorno' => $mov->id_unidade_negocio_retorno,
                ],
            );
        });
    }
}
