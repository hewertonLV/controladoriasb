<?php

namespace App\Services\UnidadesNegocio;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita a leitura do PhpSpreadsheet às colunas A:K (layout fixo da
 * importação de Unidades de Negócio) e a uma janela máxima de linhas.
 */
class UnidadeNegocioImportacaoReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxLinha = 5000) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= 1
            && $row <= $this->maxLinha
            && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'], true);
    }
}
