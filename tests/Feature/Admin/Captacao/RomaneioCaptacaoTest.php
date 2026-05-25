<?php

namespace Tests\Feature\Admin\Captacao;

use App\Services\Captacao\RomaneioAbastecimentoService;
use App\Services\Captacao\RomaneioCarregamentoService;

class RomaneioCaptacaoTest extends CaptacaoTestCase
{
    public function test_romaneio_carregamento_agrupa_por_loja_e_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();

        $lote = $this->criarLoteCaptacao($c);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 5]],
        ]);

        $c['fruta']->update([
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $preview = app(RomaneioCarregamentoService::class)->preview($lote->fresh());
        $loja = $preview->first();

        $this->assertCount(1, $preview);
        $this->assertSame($c['cliente']->id, $loja['id_cliente']);
        $this->assertSame('Rota Teste', $loja['rota_nome']);
        $this->assertCount(1, $loja['itens']);
        $this->assertSame('CAIXA', $loja['itens'][0]['unidade_medicao']);
        $this->assertSame('5,00', $loja['itens'][0]['quantidade_um_formatado']);
        $this->assertSame('50,00', $loja['itens'][0]['quantidade_kg_formatado']);
        $this->assertSame('5,00', $loja['totais_por_um'][0]['quantidade_formatado']);
        $this->assertSame('50,00', $loja['total_kg_formatado']);

        $gerais = app(RomaneioCarregamentoService::class)->totaisGerais($preview);
        $this->assertSame('50,00', $gerais['total_kg_formatado']);
        $this->assertSame('5,00', $gerais['totais_por_um'][0]['quantidade_formatado']);
    }

    public function test_romaneio_abastecimento_calcula_kg_um_e_unidade_medicao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $c['fruta']->update([
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $lote = $this->criarLoteCaptacao($c);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 5]],
        ]);

        $linha = app(RomaneioAbastecimentoService::class)->preview($lote->fresh())->first();

        $this->assertNotNull($linha);
        $this->assertSame('CAIXA', $linha['unidade_medicao']);
        $this->assertSame('50.00', $linha['demanda_kg']);
        $this->assertSame('5.00', $linha['demanda_um']);
        $this->assertSame('50,00', $linha['demanda_kg_formatado']);
        $this->assertSame('5,00', $linha['demanda_um_formatado']);
        $this->assertSame('50.00', $linha['a_receber_kg']);
        $this->assertSame('5.00', $linha['a_receber_um']);
    }
}
