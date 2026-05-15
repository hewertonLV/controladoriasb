<?php

namespace App\Services\Estoques;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita leitura às colunas A:D (importação de estoques).
 */
class EstoqueImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B', 'C', 'D'], true);
    }
}
