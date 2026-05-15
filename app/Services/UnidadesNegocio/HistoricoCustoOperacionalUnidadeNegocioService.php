<?php

namespace App\Services\UnidadesNegocio;

use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Facades\DB;

/**
 * Registra alterações de custo_operacional em historico_c_o_un_ng.
 */
class HistoricoCustoOperacionalUnidadeNegocioService
{
    public function registrarSeNecessario(UnidadeNegocio $unidade, ?string $custoAnterior = null): void
    {
        if (! $unidade->exists || $unidade->id === null) {
            return;
        }

        $custoAtual = $this->normalizarDecimal($unidade->custo_operacional);

        if ($custoAnterior !== null && $this->decimaisIguais($custoAnterior, $custoAtual)) {
            return;
        }

        if ($custoAnterior === null) {
            $vigente = HistoricoCOUnNg::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->vigente()
                ->first();

            if ($vigente !== null && $this->decimaisIguais($vigente->custo_operacional, $custoAtual)) {
                return;
            }
        }

        DB::transaction(function () use ($unidade, $custoAtual): void {
            HistoricoCOUnNg::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('status_position', true)
                ->update(['status_position' => false]);

            HistoricoCOUnNg::query()->create([
                'id_unidade_negocio' => $unidade->id,
                'custo_operacional' => $custoAtual,
                'status_position' => true,
            ]);
        });
    }

    public function decimaisIguais(mixed $a, mixed $b): bool
    {
        return bccomp($this->normalizarDecimal($a), $this->normalizarDecimal($b), 2) === 0;
    }

    public function normalizarDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
