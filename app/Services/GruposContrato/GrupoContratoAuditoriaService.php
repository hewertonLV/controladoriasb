<?php

namespace App\Services\GruposContrato;

use App\Models\GrupoContrato;
use App\Models\GrupoContratoCliente;
use App\Models\GrupoContratoClienteHistorico;
use App\Models\GrupoContratoDesconto;
use App\Models\GrupoContratoDescontoHistorico;
use App\Models\GrupoContratoHistorico;
use App\Models\User;

class GrupoContratoAuditoriaService
{
    public function registrarCriacaoGrupo(GrupoContrato $grupo, ?User $user): GrupoContratoHistorico
    {
        return GrupoContratoHistorico::create([
            'grupo_contrato_id' => $grupo->id,
            'user_id' => $user?->id,
            'origem' => GrupoContratoHistorico::ORIGEM_MANUAL,
            'acao' => GrupoContratoHistorico::ACAO_CRIACAO,
            'dados_antes' => null,
            'dados_depois' => $this->snapshotGrupo($grupo),
            'alteracoes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $antes
     */
    public function registrarAtualizacaoGrupo(GrupoContrato $grupo, array $antes, ?User $user): ?GrupoContratoHistorico
    {
        $depois = $this->snapshotGrupo($grupo);
        $diff = $this->diff($antes, $depois);

        if ($diff === []) {
            return null;
        }

        return GrupoContratoHistorico::create([
            'grupo_contrato_id' => $grupo->id,
            'user_id' => $user?->id,
            'origem' => GrupoContratoHistorico::ORIGEM_MANUAL,
            'acao' => GrupoContratoHistorico::ACAO_ATUALIZACAO,
            'dados_antes' => $antes,
            'dados_depois' => $depois,
            'alteracoes' => $diff,
        ]);
    }

    public function registrarCriacaoMembro(GrupoContratoCliente $membro, ?User $user): GrupoContratoClienteHistorico
    {
        return GrupoContratoClienteHistorico::create([
            'grupo_contrato_cliente_id' => $membro->id,
            'grupo_contrato_id' => $membro->grupo_contrato_id,
            'cliente_id' => $membro->cliente_id,
            'user_id' => $user?->id,
            'origem' => GrupoContratoClienteHistorico::ORIGEM_MANUAL,
            'acao' => GrupoContratoClienteHistorico::ACAO_CRIACAO,
            'dados_antes' => null,
            'dados_depois' => $this->snapshotMembro($membro),
            'alteracoes' => null,
        ]);
    }

    public function registrarCriacaoDesconto(GrupoContratoDesconto $desconto, ?User $user): GrupoContratoDescontoHistorico
    {
        return GrupoContratoDescontoHistorico::create([
            'grupo_contrato_desconto_id' => $desconto->id,
            'grupo_contrato_id' => $desconto->grupo_contrato_id,
            'user_id' => $user?->id,
            'origem' => GrupoContratoDescontoHistorico::ORIGEM_MANUAL,
            'acao' => GrupoContratoDescontoHistorico::ACAO_CRIACAO,
            'dados_antes' => null,
            'dados_depois' => $this->snapshotDesconto($desconto),
            'alteracoes' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotGrupo(GrupoContrato $grupo): array
    {
        return [
            'nome' => $grupo->nome,
            'descricao' => $grupo->descricao,
            'ativo' => (bool) $grupo->ativo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotMembro(GrupoContratoCliente $membro): array
    {
        return [
            'grupo_contrato_id' => (int) $membro->grupo_contrato_id,
            'cliente_id' => (int) $membro->cliente_id,
            'competencia_inicio' => $membro->competencia_inicio,
            'competencia_fim' => $membro->competencia_fim,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotDesconto(GrupoContratoDesconto $desconto): array
    {
        return [
            'grupo_contrato_id' => (int) $desconto->grupo_contrato_id,
            'competencia' => $desconto->competencia,
            'valor' => (string) $desconto->valor,
            'valor_teto' => $desconto->valor_teto !== null ? (string) $desconto->valor_teto : null,
            'observacao' => $desconto->observacao,
        ];
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return list<array{campo:string, antes:mixed, depois:mixed}>
     */
    private function diff(array $antes, array $depois): array
    {
        $alteracoes = [];

        foreach ($depois as $campo => $valorDepois) {
            $valorAntes = $antes[$campo] ?? null;
            if ($valorAntes !== $valorDepois) {
                $alteracoes[] = [
                    'campo' => $campo,
                    'antes' => $valorAntes,
                    'depois' => $valorDepois,
                ];
            }
        }

        return $alteracoes;
    }
}
