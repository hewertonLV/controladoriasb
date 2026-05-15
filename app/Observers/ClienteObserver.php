<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Services\Empresas\EmpresaRegistryService;

class ClienteObserver
{
    public function __construct(
        private readonly EmpresaRegistryService $empresaRegistry,
    ) {}

    public function created(Cliente $cliente): void
    {
        $this->empresaRegistry->garantirRegistro($cliente, auth()->user());
    }

    public function deleted(Cliente $cliente): void
    {
        $this->empresaRegistry->removerRegistroSeExistir($cliente);
    }
}
