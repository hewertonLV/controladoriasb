<?php

namespace App\Services\Movimentacoes;

use App\Models\CategoriaMovimentacao;
use App\Models\Fruta;
use App\Models\Movimentacao;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Camada base para movimentações (estoque, custo médio, integração com `movimentacoes_estoque`).
 *
 * Regras específicas por categoria ficam nos serviços dedicados (Compra, Venda, …).
 */
class MovimentacaoService
{
    /**
     * Garante `qtd_fruta_um = qtd_fruta_kg / fruta.kg_por_unidade_medicao` sem divisão por zero.
     */
    public function sincronizarQuantidadeUnidadeMedida(Movimentacao $movimentacao): void
    {
        $idFruta = (int) ($movimentacao->getAttributes()['id_fruta'] ?? $movimentacao->id_fruta ?? 0);
        if ($idFruta < 1) {
            return;
        }

        $fruta = $movimentacao->relationLoaded('fruta')
            ? $movimentacao->getRelation('fruta')
            : Fruta::query()->find($idFruta);

        if (! $fruta instanceof Fruta) {
            return;
        }

        $qtdKgRaw = $movimentacao->getAttributes()['qtd_fruta_kg'] ?? null;
        $qtdKg = $qtdKgRaw !== null && $qtdKgRaw !== '' ? (float) $qtdKgRaw : (float) $movimentacao->qtd_fruta_kg;

        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            $movimentacao->setAttribute('qtd_fruta_um', number_format(0, 2, '.', ''));

            return;
        }

        $um = round($qtdKg / $kgPorUm, 2);
        $movimentacao->setAttribute('qtd_fruta_um', number_format(max(0, $um), 2, '.', ''));
    }

    /**
     * Valida chaves estrangeiras obrigatórias e coerência mínima antes da persistência.
     *
     * @param  array<string, mixed>  $dados
     */
    public function validarReferencias(array $dados): void
    {
        $idFruta = (int) ($dados['id_fruta'] ?? 0);
        if ($idFruta < 1 || ! Fruta::query()->whereKey($idFruta)->exists()) {
            throw new InvalidArgumentException('Fruta inválida ou inexistente.');
        }

        $idCategoria = (int) ($dados['categoria_movimentacao_id'] ?? 0);
        if ($idCategoria < 1 || ! CategoriaMovimentacao::query()->whereKey($idCategoria)->exists()) {
            throw new InvalidArgumentException('Categoria de movimentação inválida ou inexistente.');
        }

        foreach (['id_movimentacao_estoque_old', 'id_movimentacao_estoque_new', 'id_empresa_origem', 'id_empresa_destino', 'id_frete', 'id_custo_operacional'] as $campoFk) {
            $this->assertFkOpcionalExiste($dados, $campoFk, $this->tabelaPorCampoMovimentacao($campoFk));
        }
    }

    /**
     * Hook pós-create (estoque, auditoria). Intencionalmente vazio nesta fase.
     */
    public function aposCriar(Movimentacao $movimentacao): void
    {
        //
    }

    /**
     * Hook pós-update. Intencionalmente vazio nesta fase.
     */
    public function aposAtualizar(Movimentacao $movimentacao): void
    {
        //
    }

    /**
     * Hook antes de apagar. Intencionalmente vazio nesta fase.
     */
    public function antesDeApagar(Movimentacao $movimentacao): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function assertFkOpcionalExiste(array $dados, string $campo, string $tabela): void
    {
        $id = $dados[$campo] ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $idInt = (int) $id;
        if ($idInt < 1 || ! DB::table($tabela)->where('id', $idInt)->exists()) {
            throw new InvalidArgumentException("Referência inválida para {$campo}.");
        }
    }

    private function tabelaPorCampoMovimentacao(string $campo): string
    {
        return match ($campo) {
            'id_movimentacao_estoque_old', 'id_movimentacao_estoque_new' => 'movimentacoes_estoque',
            'id_empresa_origem', 'id_empresa_destino' => 'empresas',
            'id_frete' => 'fretes',
            'id_custo_operacional' => 'historico_c_o_un_ng',
            default => throw new InvalidArgumentException('Campo FK desconhecido.'),
        };
    }
}
