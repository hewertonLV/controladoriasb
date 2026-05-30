<?php

namespace App\Services\Captacao;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class CaptacaoCarteiraImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row < 1 || $row > $this->maxLinha) {
            return false;
        }

        return $columnAddress === 'A';
    }
}
