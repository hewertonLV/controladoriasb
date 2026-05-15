<?php

namespace App\Enums;

use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

enum TipoEmpresaRegistro: string
{
    case CLIENTE = 'CLIENTE';
    case FORNECEDOR = 'FORNECEDOR';
    case UNIDADE_NEGOCIO = 'UNIDADE_NEGOCIO';

    /**
     * @return class-string<Model>
     */
    public function classeModelo(): string
    {
        return match ($this) {
            self::CLIENTE => Cliente::class,
            self::FORNECEDOR => Fornecedor::class,
            self::UNIDADE_NEGOCIO => UnidadeNegocio::class,
        };
    }

    public static function fromClass(string $class): self
    {
        return match ($class) {
            Cliente::class => self::CLIENTE,
            Fornecedor::class => self::FORNECEDOR,
            UnidadeNegocio::class => self::UNIDADE_NEGOCIO,
            default => throw new InvalidArgumentException('Classe não mapeada para registro corporativo: '.$class),
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::CLIENTE => 'Cliente',
            self::FORNECEDOR => 'Fornecedor',
            self::UNIDADE_NEGOCIO => 'Unidade de negócio',
        };
    }
}
