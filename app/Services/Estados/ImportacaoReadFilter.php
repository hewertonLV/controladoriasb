<?php

namespace App\Services\Estados;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita a leitura do PhpSpreadsheet às colunas A–D e a uma janela máxima de linhas.
 */
class ImportacaoReadFilter implements IReadFilter
{
    private const COLUNAS = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, self::COLUNAS, true);
    }
}
