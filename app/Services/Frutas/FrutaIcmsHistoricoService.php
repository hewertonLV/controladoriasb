<?php

namespace App\Services\Frutas;

use App\Models\Fruta;
use App\Models\FrutaIcmsHistorico;
use App\Models\User;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FrutaIcmsHistoricoService
{
    /**
     * @param  array<string, mixed>  $linha
     */
    public function registrarSeAlterou(
        Fruta $fruta,
        int $idEstado,
        array $linha,
        ?User $user = null,
        string $origem = FrutaIcmsHistorico::ORIGEM_MANUAL,
    ): void {
        $snapshot = FrutaIcmsHistorico::fromLinhaIcms($fruta->id, $idEstado, $linha);

        $vigente = FrutaIcmsHistorico::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->vigente()
            ->first();

        if ($vigente !== null && $this->snapshotsIguais($vigente, $snapshot)) {
            return;
        }

        DB::transaction(function () use ($fruta, $idEstado, $snapshot, $user, $origem): void {
            FrutaIcmsHistorico::query()
                ->where('fruta_id', $fruta->id)
                ->where('id_estado', $idEstado)
                ->where('status_position', true)
                ->update(['status_position' => false]);

            FrutaIcmsHistorico::query()->create([
                'fruta_id' => $fruta->id,
                'id_estado' => $idEstado,
                'user_id' => $user?->id,
                'origem' => $origem,
                'aliquotas' => $snapshot->aliquotas,
                'status_position' => true,
                'created_at' => now(),
            ]);
        });
    }

    public function vigenteNaData(int $frutaId, int $idEstado, Carbon|\DateTimeInterface $data): ?FrutaIcmsHistorico
    {
        return FrutaIcmsHistorico::query()
            ->where('fruta_id', $frutaId)
            ->where('id_estado', $idEstado)
            ->where('created_at', '<=', $data)
            ->orderByDesc('created_at')
            ->first();
    }

    private function snapshotsIguais(FrutaIcmsHistorico $a, FrutaIcmsHistorico $b): bool
    {
        return $a->aliquotasArray() === $b->aliquotasArray();
    }
}
