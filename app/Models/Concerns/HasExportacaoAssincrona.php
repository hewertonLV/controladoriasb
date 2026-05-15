<?php

namespace App\Models\Concerns;

trait HasExportacaoAssincrona
{
    public const TIPO_PDF = 'PDF';

    public const STATUS_AGUARDANDO = 'AGUARDANDO';

    public const STATUS_PROCESSANDO = 'PROCESSANDO';

    public const STATUS_CONCLUIDO = 'CONCLUIDO';

    public const STATUS_FALHOU = 'FALHOU';

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isConcluido(): bool
    {
        return $this->status === self::STATUS_CONCLUIDO;
    }

    public function isFalhou(): bool
    {
        return $this->status === self::STATUS_FALHOU;
    }

    public function isFinalizado(): bool
    {
        return $this->isConcluido() || $this->isFalhou();
    }
}
