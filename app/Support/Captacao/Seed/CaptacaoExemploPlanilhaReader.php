<?php

namespace App\Support\Captacao\Seed;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class CaptacaoExemploPlanilhaReader
{
    private const COL_CLIENTE = 'A';

    private const COL_FRUTA = 'B';

    private const COL_NUMERO_PEDIDO = 'C';

    private const COL_QUANTIDADE = 'D';

    private const COL_PRECO_TABELA = 'E';

    private const COL_PRECO_PROMO = 'F';

    /**
     * @return list<array{
     *     codigo_cliente: string,
     *     numero_pedido: string|null,
     *     itens: list<array{
     *         codigo_fruta: string,
     *         quantidade: string,
     *         preco_venda: string|null,
     *         linha: int,
     *     }>
     * }>
     */
    public function lerArquivo(string $caminhoAbsoluto): array
    {
        if (! is_file($caminhoAbsoluto)) {
            throw new \RuntimeException("Planilha não encontrada: {$caminhoAbsoluto}");
        }

        $reader = IOFactory::createReaderForFile($caminhoAbsoluto);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($caminhoAbsoluto);
        $sheet = $spreadsheet->getActiveSheet();

        $pedidos = [];
        $indicePorCliente = [];
        $clienteCorrente = null;

        $maxRow = (int) $sheet->getHighestDataRow();

        for ($linha = 2; $linha <= $maxRow; $linha++) {
            $codigoCliente = trim((string) $sheet->getCell(self::COL_CLIENTE.$linha)->getCalculatedValue());
            if ($codigoCliente !== '') {
                $clienteCorrente = $codigoCliente;
            }

            if ($clienteCorrente === null) {
                continue;
            }

            $codigoFruta = trim((string) $sheet->getCell(self::COL_FRUTA.$linha)->getCalculatedValue());
            if ($codigoFruta === '') {
                continue;
            }

            $quantidadeBruta = $sheet->getCell(self::COL_QUANTIDADE.$linha)->getCalculatedValue();
            $quantidade = $this->normalizarQuantidade($quantidadeBruta);
            if ($quantidade === null || (float) $quantidade <= 0) {
                continue;
            }

            $precoTabela = CaptacaoExemploPlanilhaPreco::parseValorBr(
                $sheet->getCell(self::COL_PRECO_TABELA.$linha)->getCalculatedValue(),
            );
            $precoPromo = CaptacaoExemploPlanilhaPreco::parseValorBr(
                $sheet->getCell(self::COL_PRECO_PROMO.$linha)->getCalculatedValue(),
            );
            $precoEfetivo = CaptacaoExemploPlanilhaPreco::precoEfetivo($precoPromo, $precoTabela);

            $numeroPedido = trim((string) $sheet->getCell(self::COL_NUMERO_PEDIDO.$linha)->getCalculatedValue());
            $numeroPedido = $numeroPedido !== '' ? $numeroPedido : null;

            if (! isset($indicePorCliente[$clienteCorrente])) {
                $indicePorCliente[$clienteCorrente] = count($pedidos);
                $pedidos[] = [
                    'codigo_cliente' => $clienteCorrente,
                    'numero_pedido' => $numeroPedido,
                    'itens' => [],
                ];
            }

            $idx = $indicePorCliente[$clienteCorrente];

            if ($numeroPedido !== null && ($pedidos[$idx]['numero_pedido'] === null || $pedidos[$idx]['numero_pedido'] === '')) {
                $pedidos[$idx]['numero_pedido'] = $numeroPedido;
            }

            $pedidos[$idx]['itens'][] = [
                'codigo_fruta' => $codigoFruta,
                'quantidade' => $quantidade,
                'preco_venda' => $precoEfetivo !== null
                    ? number_format($precoEfetivo, 4, '.', '')
                    : null,
                'linha' => $linha,
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);

        return $pedidos;
    }

    private function normalizarQuantidade(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return number_format((float) $valor, 3, '.', '');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(',', '.', $texto);

        if (! is_numeric($texto)) {
            return null;
        }

        return number_format((float) $texto, 3, '.', '');
    }
}
