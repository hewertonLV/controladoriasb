<?php

namespace App\Enums;

enum AppModulo: string
{
    case Administrador = 'administrador';
    case Captacao = 'captacao';
    case Centralizador = 'centralizador';
    case Transferencia = 'transferencia';
    case Venda = 'venda';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Captacao => 'Captação',
            self::Centralizador => 'Centralizador',
            self::Transferencia => 'Transferência',
            self::Venda => 'Venda',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Administrador => 'Dashboard, cadastros, movimentações e relatórios completos.',
            self::Captacao => 'Lotes, pedidos por loja, matriz e romaneio de captação.',
            self::Centralizador => 'Pipeline administrativo de lotes de captação.',
            self::Transferencia => 'Transferências entre unidades e estoque.',
            self::Venda => 'Registro e acompanhamento de vendas.',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Administrador => 'ri-settings-3-line',
            self::Captacao => 'ri-shopping-basket-line',
            self::Centralizador => 'ri-stack-line',
            self::Transferencia => 'ri-truck-line',
            self::Venda => 'ri-store-2-line',
        };
    }

    public function corBootstrap(): string
    {
        return match ($this) {
            self::Administrador => 'primary',
            self::Captacao => 'success',
            self::Centralizador => 'secondary',
            self::Transferencia => 'info',
            self::Venda => 'warning',
        };
    }

    public function exibeSidebarAdministrativa(): bool
    {
        return $this === self::Administrador;
    }

    public function usaTopbarModuloCaptacao(): bool
    {
        return in_array($this, [self::Captacao, self::Centralizador], true);
    }

    public function urlEntrada(): string
    {
        return match ($this) {
            self::Administrador => route('dashboard'),
            self::Captacao => route('admin.captacao.pedidos-por-loja.carteiras'),
            self::Centralizador => route('admin.captacao.lotes.index'),
            self::Transferencia => route('admin.movimentacoes.transferencias.index'),
            self::Venda => route('admin.movimentacoes.vendas.index'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $modulo) => $modulo->value, self::cases());
    }

    public static function tryFromSession(): ?self
    {
        $valor = session('app_modulo');

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return self::tryFrom($valor);
    }
}
