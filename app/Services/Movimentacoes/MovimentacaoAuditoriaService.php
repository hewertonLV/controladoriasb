<?php

namespace App\Services\Movimentacoes;

use App\Models\Movimentacao;
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
            'qtd_fruta_um' => (string) $movimentacao->qtd_fruta_um,
            'qtd_fruta_kg' => (string) $movimentacao->qtd_fruta_kg,
            'valor_frete_kg' => (string) $movimentacao->valor_frete_kg,
            'valor_frete_rateio' => (string) $movimentacao->valor_frete_rateio,
            'preco_medio_fruta_kg' => (string) $movimentacao->preco_medio_fruta_kg,
            'preco_medio_fruta_um' => (string) $movimentacao->preco_medio_fruta_um,
            'id_frete' => $movimentacao->id_frete,
            'id_movimentacao_estoque_new' => $movimentacao->id_movimentacao_estoque_new,
        ];
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
}
