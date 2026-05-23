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
            'numero_compra' => $movimentacao->numero_compra,
            'versao' => $movimentacao->versao,
            'status_registro' => $movimentacao->status_registro,
            'movimentacao_origem_id' => $movimentacao->movimentacao_origem_id,
            'data_movimentacao' => (string) $movimentacao->data_movimentacao,
            'valor_nf_total' => (string) $movimentacao->valor_nf_total,
            'valor_nf_um' => (string) $movimentacao->valor_nf_um,
            'valor_nf_kg' => (string) $movimentacao->valor_nf_kg,
            'valor_total_movimentacao' => (string) ($movimentacao->valor_total_movimentacao ?? '0.00'),
            'valor_custo_saida' => (string) ($movimentacao->valor_custo_saida ?? '0.00'),
            'resultado_movimentacao' => (string) ($movimentacao->resultado_movimentacao ?? '0.00'),
            'valor_icms_total' => (string) ($movimentacao->valor_icms_total ?? '0.00'),
            'valor_icms_kg' => (string) ($movimentacao->valor_icms_kg ?? '0.00'),
            'valor_icms_um' => (string) ($movimentacao->valor_icms_um ?? '0.00'),
            'icms_convertido_kg' => (string) ($movimentacao->icms_convertido_kg ?? '0.00'),
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
            'versao_replay' => (int) ($movimentacao->versao_replay ?? 1),
            'categoria_descarte_id' => $movimentacao->categoria_descarte_id,
            'venda_nota_id' => $movimentacao->venda_nota_id,
            'numero_nf' => $movimentacao->vendaNota?->numero_nf,
            'id_unidade_negocio_faturamento' => $movimentacao->id_unidade_negocio_faturamento,
            'id_unidade_negocio_estoque' => $movimentacao->id_unidade_negocio_estoque,
            'id_unidade_negocio_retorno' => $movimentacao->id_unidade_negocio_retorno,
            'movimentacao_venda_origem_id' => $movimentacao->movimentacao_venda_origem_id,
            'devolucao_origem_id' => $movimentacao->devolucao_origem_id,
            'conversao_origem_id' => $movimentacao->conversao_origem_id,
            'id_fruta_destino_conversao' => $movimentacao->id_fruta_destino_conversao,
            'qtd_resultante_um' => (string) ($movimentacao->qtd_resultante_um ?? '0.00'),
            'qtd_resultante_kg' => (string) ($movimentacao->qtd_resultante_kg ?? '0.00'),
            'qtd_perda_conversao_um' => (string) ($movimentacao->qtd_perda_conversao_um ?? '0.00'),
            'qtd_perda_conversao_kg' => (string) ($movimentacao->qtd_perda_conversao_kg ?? '0.00'),
            'valor_perda_conversao' => (string) ($movimentacao->valor_perda_conversao ?? '0.00'),
            'tipo_devolucao' => $movimentacao->tipo_devolucao,
            'numero_nf_devolucao' => $movimentacao->numero_nf_devolucao,
            'motivo_devolucao' => $movimentacao->motivo_devolucao,
            'valor_devolucao_total' => (string) ($movimentacao->valor_devolucao_total ?? '0.00'),
            'valor_devolucao_um' => (string) ($movimentacao->valor_devolucao_um ?? '0.00'),
            'valor_devolucao_kg' => (string) ($movimentacao->valor_devolucao_kg ?? '0.00'),
            'valor_custo_devolucao' => (string) ($movimentacao->valor_custo_devolucao ?? '0.00'),
            'resultado_devolucao' => (string) ($movimentacao->resultado_devolucao ?? '0.00'),
            'id_frete' => $movimentacao->id_frete,
            'id_movimentacao_estoque_new' => $movimentacao->id_movimentacao_estoque_new,
            'cancelada_por' => $movimentacao->cancelada_por,
            'cancelada_em' => $movimentacao->cancelada_em?->toIso8601String(),
            'motivo_cancelamento' => $movimentacao->motivo_cancelamento,
            'status_transferencia' => $movimentacao->status_transferencia,
            'motivo_doacao' => $movimentacao->motivo_doacao !== null && $movimentacao->motivo_doacao !== ''
                ? (string) $movimentacao->motivo_doacao
                : null,
            'motivo_descarte' => $movimentacao->motivo_descarte !== null && $movimentacao->motivo_descarte !== ''
                ? (string) $movimentacao->motivo_descarte
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

    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    public function registrarRegistroEntradaEstoque(
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
            'origem' => MovimentacaoHistorico::ORIGEM_ENTRADA_ESTOQUE,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_ENTRADA_ESTOQUE,
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

    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    public function registrarRegistroDescarte(
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
            'origem' => MovimentacaoHistorico::ORIGEM_DESCARTE,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DESCARTE,
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

    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    public function registrarRegistroVenda(
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
            'origem' => MovimentacaoHistorico::ORIGEM_VENDA,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_VENDA,
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

    /**
     * @param  array<string, mixed>|null  $meAntes
     * @param  array<string, mixed>|null  $meDepois
     */
    public function registrarRegistroDevolucao(
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
            'origem' => MovimentacaoHistorico::ORIGEM_DEVOLUCAO,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DEVOLUCAO,
            'motivo' => null,
            'dados_antes' => [
                'estoque' => $estoqueAntes,
                'movimentacao_estoque' => $meAntes,
            ],
            'dados_depois' => [
                'movimentacao' => $this->snapshotVersao($movimentacao),
                'estoque' => $estoqueDepois,
                'movimentacao_estoque' => $meDepois,
                'venda_origem' => $movimentacao->vendaOrigem !== null
                    ? $this->snapshotVersao($movimentacao->vendaOrigem)
                    : null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meSaidaAntes
     * @param  array<string, mixed>|null  $meSaidaDepois
     * @param  array<string, mixed>|null  $meEntradaAntes
     * @param  array<string, mixed>|null  $meEntradaDepois
     */
    public function registrarRegistroConversaoEmbalagem(
        Movimentacao $saida,
        Movimentacao $entrada,
        ?User $user,
        array $estoqueOrigemAntes,
        array $estoqueOrigemDepois,
        array $estoqueDestinoAntes,
        array $estoqueDestinoDepois,
        ?array $meSaidaAntes,
        ?array $meSaidaDepois,
        ?array $meEntradaAntes,
        ?array $meEntradaDepois,
    ): MovimentacaoHistorico {
        return MovimentacaoHistorico::query()->create([
            'movimentacao_cadeia_raiz_id' => $saida->idCadeiaRaiz(),
            'movimentacao_antes_id' => $saida->id,
            'movimentacao_depois_id' => $entrada->id,
            'user_id' => $user?->id,
            'origem' => MovimentacaoHistorico::ORIGEM_CONVERSAO_EMBALAGEM,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_CONVERSAO_EMBALAGEM,
            'motivo' => null,
            'dados_antes' => [
                'estoque_origem' => $estoqueOrigemAntes,
                'movimentacao_estoque_origem' => $meSaidaAntes,
                'estoque_destino' => $estoqueDestinoAntes,
                'movimentacao_estoque_destino' => $meEntradaAntes,
            ],
            'dados_depois' => [
                'saida' => $this->snapshotVersao($saida),
                'entrada' => $this->snapshotVersao($entrada),
                'estoque_origem' => $estoqueOrigemDepois,
                'movimentacao_estoque_origem' => $meSaidaDepois,
                'estoque_destino' => $estoqueDestinoDepois,
                'movimentacao_estoque_destino' => $meEntradaDepois,
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
