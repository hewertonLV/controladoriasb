<?php

namespace App\Support\Modulos;

use App\Enums\AppModulo;

final readonly class ModuloCard
{
    public function __construct(
        public AppModulo $modulo,
        public string $urlEntrada,
    ) {}
}
