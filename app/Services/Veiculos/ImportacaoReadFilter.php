<?php

namespace App\Services\Veiculos;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita a leitura do PhpSpreadsheet às colunas A:E e a uma janela
 * máxima de linhas físicas.
 */
class ImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E'], true);
    }
}
