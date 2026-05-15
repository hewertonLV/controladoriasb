<?php

namespace App\Observers;

use App\Models\Fornecedor;
use App\Services\Empresas\EmpresaRegistryService;

class FornecedorObserver
{
    public function __construct(
        private readonly EmpresaRegistryService $empresaRegistry,
    ) {}

    public function created(Fornecedor $fornecedor): void
    {
        $this->empresaRegistry->garantirRegistro($fornecedor, auth()->user());
    }

    public function deleted(Fornecedor $fornecedor): void
    {
        $this->empresaRegistry->removerRegistroSeExistir($fornecedor);
    }
}
