<?php

namespace App\Services\GruposContrato;

use App\Models\GrupoContrato;
use App\Models\GrupoContratoDesconto;
use App\Models\User;

class GrupoContratoDescontoService
{
    public function __construct(
        private readonly GrupoContratoAuditoriaService $auditoria,
    ) {}

    /**
     * @param  array{competencia:string, valor:string, valor_teto?:string|null, observacao?:string|null}  $dados
     */
    public function lancar(GrupoContrato $grupo, array $dados, ?User $user = null): GrupoContratoDesconto
    {
        $desconto = GrupoContratoDesconto::create([
            'grupo_contrato_id' => $grupo->id,
            'competencia' => $dados['competencia'],
            'valor' => $dados['valor'],
            'valor_teto' => $dados['valor_teto'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->auditoria->registrarCriacaoDesconto($desconto, $user);

        return $desconto;
    }
}
