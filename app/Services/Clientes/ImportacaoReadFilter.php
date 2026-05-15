<?php

namespace App\Services\Clientes;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], true);
    }
}
