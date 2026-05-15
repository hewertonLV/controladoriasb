<?php

namespace App\Services\Pracas;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita a leitura do PhpSpreadsheet às colunas A:B e a uma janela
 * máxima de linhas físicas.
 */
class ImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B'], true);
    }
}
