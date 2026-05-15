<?php

namespace App\Observers;

use App\Models\UnidadeNegocio;
use App\Services\Empresas\EmpresaRegistryService;
use App\Services\UnidadesNegocio\HistoricoCustoOperacionalUnidadeNegocioService;

class UnidadeNegocioObserver
{
    public function __construct(
        private readonly HistoricoCustoOperacionalUnidadeNegocioService $historicoService,
        private readonly EmpresaRegistryService $empresaRegistry,
    ) {}

    public function created(UnidadeNegocio $unidade): void
    {
        $this->historicoService->registrarSeNecessario($unidade);
        $this->empresaRegistry->garantirRegistro($unidade, auth()->user());
    }

    public function updated(UnidadeNegocio $unidade): void
    {
        if (! $unidade->wasChanged('custo_operacional')) {
            return;
        }

        $custoAnterior = $unidade->getOriginal('custo_operacional');

        $this->historicoService->registrarSeNecessario(
            $unidade,
            $custoAnterior === null ? null : (string) $custoAnterior,
        );
    }

    public function deleted(UnidadeNegocio $unidade): void
    {
        $this->empresaRegistry->removerRegistroSeExistir($unidade);
    }
}
