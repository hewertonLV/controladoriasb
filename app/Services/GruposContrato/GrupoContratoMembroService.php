<?php

namespace App\Services\GruposContrato;

use App\Models\GrupoContrato;
use App\Models\GrupoContratoCliente;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GrupoContratoMembroService
{
    public function __construct(
        private readonly GrupoContratoAuditoriaService $auditoria,
    ) {}

    /**
     * @param  array{cliente_id:int, competencia_inicio:string, competencia_fim?:string|null}  $dados
     */
    public function adicionar(GrupoContrato $grupo, array $dados, ?User $user = null): GrupoContratoCliente
    {
        if ($this->existeSobreposicao($grupo, $dados['cliente_id'], $dados['competencia_inicio'], $dados['competencia_fim'] ?? null)) {
            throw ValidationException::withMessages([
                'competencia_inicio' => 'Este cliente já possui participação neste grupo durante a competência informada.',
            ]);
        }

        $membro = GrupoContratoCliente::create([
            'grupo_contrato_id' => $grupo->id,
            'cliente_id' => $dados['cliente_id'],
            'competencia_inicio' => $dados['competencia_inicio'],
            'competencia_fim' => $dados['competencia_fim'] ?? null,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->auditoria->registrarCriacaoMembro($membro, $user);

        return $membro;
    }

    private function existeSobreposicao(GrupoContrato $grupo, int $clienteId, string $inicio, ?string $fim): bool
    {
        $fimComparacao = $fim ?: '9999-12';

        return GrupoContratoCliente::query()
            ->where('grupo_contrato_id', $grupo->id)
            ->where('cliente_id', $clienteId)
            ->where('competencia_inicio', '<=', $fimComparacao)
            ->where(function ($query) use ($inicio) {
                $query->whereNull('competencia_fim')
                    ->orWhere('competencia_fim', '>=', $inicio);
            })
            ->exists();
    }
}
