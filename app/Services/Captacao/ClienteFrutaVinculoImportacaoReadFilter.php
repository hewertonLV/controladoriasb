<?php

namespace App\Services\Captacao;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita a leitura às colunas A e B até a linha máxima informada.
 */
class ClienteFrutaVinculoImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row < 1 || $row > $this->maxLinha) {
            return false;
        }

        return in_array($columnAddress, ['A', 'B'], true);
    }
}
