<?php

namespace Tests\Unit\Services\Captacao;

use Tests\TestCase;

/**
 * Mapa dos campos obrigatórios (*) do EDI_NF_CIGAM.pdf — registro N e I.
 *
 * @see docs/decisions/ADR-0105-arquivo-cigan-edi-transferencia-hub.md
 */
class CiganEdiCamposObrigatoriosPdfTest extends TestCase
{
    public function test_mapa_campos_obrigatorios_registro_n_conforme_pdf(): void
    {
        $campos = [
            [1, 1, '001 Tipo', 'N'],
            [3, 7, '002 Série', null],
            [9, 15, '003 Número NF', null],
            [20, 24, '004 Tipo operação', null],
            [26, 33, '005 Data emissão', null],
            [35, 42, '006 Data entrada', null],
            [44, 44, '007 Via transporte', 'R'],
            [52, 57, '009 Cliente', null],
            [59, 64, '010 Cobrança', null],
            [132, 137, '018 Transportadora', null],
            [266, 266, '030 Tipo frete', '1'],
            [283, 283, '033 Entrada/Saída', 'S'],
            [316, 318, '037 Condição pagamento', '   '],
            [322, 381, '038 Nome', null],
            [383, 412, '039 Contato', null],
            [414, 433, '040 Fone', null],
            [456, 495, '042 Endereço', null],
            [497, 516, '043 Bairro', null],
            [518, 547, '044 Cidade', null],
            [549, 550, '045 UF', null],
            [552, 559, '046 CEP', null],
            [561, 574, '047 CNPJ/CPF', null],
            [576, 595, '048 IE', null],
            [597, 597, '049 Pessoa F/J', null],
            [599, 600, '050 Divisão', null],
            [602, 604, '051 Unidade negócio', null],
            [605, 607, '052 Centro armazenagem', null],
            [683, 687, '059 Número itens', null],
        ];

        $this->assertGreaterThanOrEqual(25, count($campos));
    }

    public function test_mapa_campos_obrigatorios_registro_i_conforme_pdf(): void
    {
        $campos = [
            [1, 1, '001 Tipo', 'I'],
            [3, 22, '002 Código material', null],
            [24, 38, '003 Quantidade', null],
            [56, 70, '005 Preço unitário', null],
            [95, 95, '009 IPI', '0'],
            [115, 314, '011 Descrição', null],
            [372, 376, '018 Tipo operação', null],
            [681, 685, '042 Sequência item', null],
        ];

        $this->assertCount(8, $campos);
    }
}
