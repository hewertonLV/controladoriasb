<?php

namespace Tests\Unit\Services\Captacao;

use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CiganEdiNfTransferenciaGerador;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CiganEdiNfTransferenciaGeradorTest extends TestCase
{
    public function test_serie_e_numero_nf_cigam_nf_mais_id_sem_zeros_a_esquerda(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $curto = $gerador->serieENumeroNotaFiscalCigam('000120');
        $this->assertSame('NF120', $curto['serie']);
        $this->assertSame(str_repeat(' ', 7), $curto['numero']);

        $longo = $gerador->serieENumeroNotaFiscalCigam('883003');
        $this->assertSame('NF883', $longo['serie']);
        $this->assertSame('003    ', $longo['numero']);
    }

    public function test_serie_e_numero_nf_cigam_exige_id_cigam(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $gerador->serieENumeroNotaFiscalCigam('');
    }

    public function test_serie_config_legacy_mantida_para_outros_usos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('  001', $gerador->serieNotaFiscal());
        $this->assertSame(5, strlen($gerador->serieNotaFiscal()));
    }

    public function test_tipo_operacao_cigam_e_5152a_transferencia(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('5152A', $gerador->tipoOperacaoCigam());
    }

    public function test_codigo_transportadora_usa_valor_fixo_padrao(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('000488', $gerador->codigoTransportadoraCigam());
    }

    public function test_condicao_pagamento_fica_em_branco_com_tres_espacos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame(str_repeat(' ', 3), $gerador->condicaoPagamentoCigam());
    }

    public function test_data_emissao_cigam_usa_dia_atual(): void
    {
        $agora = Carbon::parse('2026-06-10 14:30:00');
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('10062026', $gerador->dataEmissaoCigam($agora));
    }

    public function test_numero_divisao_cigam_usa_cadastro_do_cliente(): void
    {
        $cliente = new Cliente(['id_cigam' => '770099', 'numero_divisao' => '07']);
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('07', $gerador->numeroDivisaoCigam($cliente));
    }

    public function test_numero_divisao_cigam_usa_config_quando_cliente_sem_valor(): void
    {
        $cliente = new Cliente(['id_cigam' => '770099', 'numero_divisao' => '']);
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('10', $gerador->numeroDivisaoCigam($cliente));
    }

    public function test_codigo_cliente_cobranca_usa_id_cigam_do_cliente_vinculado_a_unidade(): void
    {
        $faturamento = new UnidadeNegocio([
            'id' => 1,
            'nome' => 'Faturamento Teste',
            'id_cigam' => '881001',
        ]);
        $cliente = new Cliente(['id_cigam' => '770099']);
        $faturamento->setRelation('clientePrincipal', $cliente);

        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('770099', $gerador->codigoClienteCobrancaCigam($faturamento));
    }

    public function test_codigo_cliente_cobranca_exige_cliente_vinculado_na_unidade(): void
    {
        $faturamento = new UnidadeNegocio([
            'id' => 1,
            'nome' => 'Faturamento Teste',
            'id_cliente' => null,
        ]);
        $faturamento->setRelation('clientePrincipal', null);

        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $gerador->codigoClienteCobrancaCigam($faturamento);
    }

    public function test_uf_cliente_usa_estado_da_unidade_de_negocio_vinculada(): void
    {
        $estado = new \App\Models\Estado(['abreviacao' => 'ce']);
        $unidade = new UnidadeNegocio(['id_estado' => 1]);
        $unidade->setRelation('estado', $estado);

        $cliente = new Cliente(['id_unidade_negocio' => 1]);
        $cliente->setRelation('unidadeNegocio', $unidade);

        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('CE', $gerador->ufClienteCigam($cliente));
    }

    public function test_codigo_material_cigam_seis_digitos_finais_com_espacos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame(
            str_repeat(' ', 14).'000123',
            $gerador->codigoMaterialCigam('ABC123'),
        );
        $this->assertSame(20, strlen($gerador->codigoMaterialCigam('999888777')));
    }

    public function test_codigo_unidade_negocio_cigam_tres_digitos_finais(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('003', $gerador->codigoUnidadeNegocioCigam('883003', 'unidade HUB de origem'));
    }

    public function test_codigo_centro_armazenagem_cigam_tres_digitos_do_cadastro(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);
        $hub = new UnidadeNegocio(['nome' => 'HUB Teste', 'centro_armazenagem' => '12']);

        $this->assertSame('012', $gerador->codigoCentroArmazenagemCigam($hub));
    }

    public function test_codigo_centro_armazenagem_cigam_rejeita_050_que_vira_50_no_erp(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);
        $hub = new UnidadeNegocio(['nome' => 'HUB Teste', 'centro_armazenagem' => '050']);

        $this->expectException(ValidationException::class);
        $gerador->codigoCentroArmazenagemCigam($hub);
    }

    public function test_formatar_quantidade_um_mascara_n86_com_seis_zeros_decimais(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('000000005000000', $gerador->formatarQuantidadeUmCigam(5.0));
        $this->assertSame('000000100000000', $gerador->formatarQuantidadeUmCigam(100.0));
        $this->assertSame('000000062500000', $gerador->formatarQuantidadeUmCigam(62.5));
        $this->assertSame('000001060000000', $gerador->formatarQuantidadeUmCigam(1060.0));
        $this->assertSame(15, strlen($gerador->formatarQuantidadeUmCigam(5.0)));
        $this->assertStringEndsWith('000000', $gerador->formatarQuantidadeUmCigam(5.0));
    }

    public function test_especie_estoque_cigam_e_sempre_s(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('S', $gerador->especieEstoqueCigam());
    }

    public function test_sequencia_item_em_branco_tem_cinco_espacos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame(str_repeat(' ', 5), $gerador->sequenciaItemEmBrancoCigam());
    }

    public function test_preco_unitario_em_branco_tem_quinze_espacos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame(str_repeat(' ', 15), $gerador->precoUnitarioEmBrancoCigam());
    }

    public function test_formatar_preco_unitario_n105_para_outros_usos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('000000001000000', $gerador->formatarPrecoUnitarioCigam(10.0));
    }

    public function test_pecas_em_branco_tem_quatorze_espacos(): void
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame(str_repeat(' ', 14), $gerador->pecasEmBrancoCigam());
    }

    public function test_data_entrada_cigam_usa_dia_atual(): void
    {
        $agora = Carbon::parse('2026-06-10 14:30:00');
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        $this->assertSame('10062026', $gerador->dataEntradaCigam($agora));
        $this->assertSame($gerador->dataEmissaoCigam($agora), $gerador->dataEntradaCigam($agora));
    }
}
