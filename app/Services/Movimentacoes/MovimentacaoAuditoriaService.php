<?php

namespace App\Services\Movimentacoes;

use App\Models\Estoque;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\User;

final class MovimentacaoAuditoriaService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshotVersao(Movimentacao $movimentacao): array
    {
        return [
            'id' => $movimentacao->id,
            'versao' => $movimentacao->versao,
            'status_registro' => $movimentacao->status_registro,
            'movimentacao_origem_id' => $movimentacao->movimentacao_origem_id,
            'data_movimentacao' => (string) $movimentacao->data_movimentacao,
            'valor_nf_total' => (string) $movimentacao->valor_nf_total,
            'valor_nf_um' => (string) $movimentacao->valor_nf_um,
            'valor_nf_kg' => (string) $movimentacao->valor_nf_kg,
            'valor_total_movimentacao' => (string) ($movimentacao->valor_total_movimentacao ?? '0.00'),
            'valor_total_acumulado' => number_format(
                round((float) $movimentacao->saldo_estoque_fruta_kg * (float) $movimentacao->preco_medio_fruta_kg, 2),
                2,
                '.',
                '',
            ),
            'qtd_fruta_um' => (string) $movimentacao->qtd_fruta_um,
            'qtd_fruta_kg' => (string) $movimentacao->qtd_fruta_kg,
            'valor_frete_kg' => (string) $movimentacao->valor_frete_kg,
            'valor_frete_rateio' => (string) $movimentacao->valor_frete_rateio,
            'preco_medio_fruta_kg' => (string) $movimentacao->preco_medio_fruta_kg,
            'preco_medio_fruta_um' => (string) $movimentacao->preco_medio_fruta_um,
            'id_frete' => $movimentacao->id_frete,
            'id_movimentacao_estoque_new' => $movimentacao->id_movimentacao_estoque_new,
            'cancelada_por' => $movimentacao->cancelada_por,
            'cancelada_em' => $movimentacao->cancelada_em?->toIso8601String(),
            'motivo_cancelamento' => $movimentacao->motivo_cancelamento,
            'status_transferencia' => $movimentacao->status_transferencia,
            'motivo_doacao' => $movimentacao->motivo_doacao !== null && $movimentacao->motivo_doacao !== ''
                ? (string) $movimentacao->motivo_doacao
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshotMovimentacaoEstoque(?MovimentacaoEstoque $me): ?array
    {
        if ($me === null) {
            return null;
        }

        return [
            'id' => $me->id,
            'movimentacao_id' => $me->movimentacao_id,
            'qtd_fruta_kg' => (string) $me->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $me->qtd_fruta_um,
            'preco_medio_kg' => (string) $me->preco_medio_kg,
            'preco_medio_um' => (string) $me->preco_medio_um,
            'valor_total_fruta' => (string) $me->valor_total_fruta,
            'status_ultima_posicao' => (bool) $me->status_ultima_posicao,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    public function registrarRegistroDoacao(
        Movimentacao $movimentacao,
        ?User $user,
        array $estoqueAntes,
        array $estoqueDepois,
        ?array $meAntes,
        ?array $meDepois,
    ): MovimentacaoHistorico {
        $raizId = $movimentacao->idCadeiaRaiz();

        return MovimentacaoHistorico::query()->create([
            'movimentacao_cadeia_raiz_id' => $raizId,
            'movimentacao_antes_id' => $movimentacao->id,
            'movimentacao_depois_id' => $movimentacao->id,
            'user_id' => $user?->id,
            'origem' => MovimentacaoHistorico::ORIGEM_DOACAO,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DOACAO,
            'motivo' => null,
            'dados_antes' => [
                'estoque' => $estoqueAntes,
                'movimentacao_estoque' => $meAntes,
            ],
            'dados_depois' => [
                'movimentacao' => $this->snapshotVersao($movimentacao),
                'estoque' => $estoqueDepois,
                'movimentacao_estoque' => $meDepois,
            ],
        ]);
    }

    public function registrarSubstituicaoDeVersao(
        Movimentacao $antes,
        Movimentacao $depois,
        ?User $user,
        ?string $motivo,
        ?array $dadosAntesSnapshot = null,
    ): MovimentacaoHistorico {
        $raizId = (int) ($antes->movimentacao_origem_id ?? $antes->id);

        return MovimentacaoHistorico::query()->create([
            'movimentacao_cadeia_raiz_id' => $raizId,
            'movimentacao_antes_id' => $antes->id,
            'movimentacao_depois_id' => $depois->id,
            'user_id' => $user?->id,
            'origem' => MovimentacaoHistorico::ORIGEM_VERSIONAMENTO,
            'acao' => MovimentacaoHistorico::ACAO_SUBSTITUICAO_VERSAO,
            'motivo' => $motivo,
            'dados_antes' => $dadosAntesSnapshot ?? $this->snapshotVersao($antes),
            'dados_depois' => $this->snapshotVersao($depois),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotEstoque(Estoque $estoque): array
    {
        return [
            'id' => $estoque->id,
            'id_unidade_negocio' => $estoque->id_unidade_negocio,
            'id_fruta' => $estoque->id_fruta,
            'qtd_fruta_kg' => (string) $estoque->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $estoque->qtd_fruta_um,
            'preco_medio_kg' => (string) $estoque->preco_medio_kg,
            'preco_medio_um' => (string) $estoque->preco_medio_um,
            'valor_total_acumulado' => (string) $estoque->valor_total_acumulado,
        ];
    }

    /**
     * @param  array<string, mixed>  $dadosMovimentacaoAntes
     * @param  array<string, mixed>  $dadosMovimentacaoDepois
     * @param  array<string, mixed>  $contextoExtra
     */
    public function registrarCancelamentoAdministrativo(
        Movimentacao $movimentacao,
        ?User $user,
        string $motivo,
        array $dadosMovimentacaoAntes,
        array $dadosMovimentacaoDepois,
        ?array $estoqueAntes = null,
        ?array $estoqueDepois = null,
        array $contextoExtra = [],
    ): MovimentacaoHistorico {
        $raizId = $movimentacao->idCadeiaRaiz();

        $antes = array_merge($dadosMovimentacaoAntes, $contextoExtra, [
            'estoque' => $estoqueAntes,
        ]);

        $depois = array_merge($dadosMovimentacaoDepois, [
            'estoque' => $estoqueDepois,
        ]);

        return MovimentacaoHistorico::query()->create([
            'movimentacao_cadeia_raiz_id' => $raizId,
            'movimentacao_antes_id' => $movimentacao->id,
            'movimentacao_depois_id' => $movimentacao->id,
            'user_id' => $user?->id,
            'origem' => MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN,
            'acao' => MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN,
            'motivo' => $motivo,
            'dados_antes' => $antes,
            'dados_depois' => $depois,
        ]);
    }
}
