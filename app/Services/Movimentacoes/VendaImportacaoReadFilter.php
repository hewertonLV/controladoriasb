<?php

namespace App\Services\Movimentacoes;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita leitura às colunas A:G (importação de vendas).
 */
class VendaImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F', 'G'], true);
    }
}
