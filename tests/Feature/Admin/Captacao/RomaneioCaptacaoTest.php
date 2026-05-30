<?php

namespace Tests\Feature\Admin\Captacao;

use App\Services\Captacao\RomaneioAbastecimentoService;
use App\Services\Captacao\RomaneioCarregamentoService;
use App\Support\Captacao\RomaneioRotaPdfNomeArquivo;

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

    public function test_romaneio_carregamento_por_rota_ordenado_e_titulo_aba(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);

        $clienteB = \App\Models\Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(\App\Services\Captacao\ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 3]],
        ]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $clienteB->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 1]],
        ]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 2,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $clienteB]), [
            'ordem_carregamento' => 1,
        ])->assertOk();

        $porRotas = app(RomaneioCarregamentoService::class)->previewPorRotas($lote->fresh());

        $this->assertCount(1, $porRotas);
        $grupo = $porRotas->first();
        $this->assertStringContainsString($c['carteira']->nome, $grupo['titulo_aba']);
        $this->assertStringContainsString('Rota Teste', $grupo['titulo_aba']);
        $this->assertCount(2, $grupo['lojas']);
        $this->assertSame(1, $grupo['lojas'][0]['ordem_carregamento']);
        $this->assertSame(2, $grupo['lojas'][1]['ordem_carregamento']);
        $this->assertSame($clienteB->id, $grupo['lojas'][0]['id_cliente']);
    }

    public function test_lote_show_exibe_romaneio_por_rota_sem_abastecimento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 2]],
        ]);

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.show', $lote))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Romaneio de carregamento', $html);
        $this->assertStringContainsString('Imprimir rota', $html);
        $this->assertStringContainsString($c['carteira']->nome, $html);
        $this->assertStringNotContainsString('Romaneio 2', $html);
        $this->assertStringNotContainsString('Abastecimento', $html);
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

    public function test_romaneio_abastecimento_exclui_pedido_com_saida_fisica_no_hub(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $c['fruta']->update([
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $hub = \App\Models\UnidadeNegocio::factory()->create([
            'is_hub' => true,
            'possui_estoque' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 5]],
        ]);

        $pedido = $lote->fresh()->pedidos()->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $linha = app(RomaneioAbastecimentoService::class)->preview($lote->fresh())->first();

        $this->assertNull($linha);

        $necessidade = app(RomaneioAbastecimentoService::class)->necessidadeEstoqueHub($lote->fresh())->first();
        $this->assertNotNull($necessidade);
        $this->assertSame('50.00', number_format($necessidade['necessidade_kg'], 2, '.', ''));
        $this->assertSame('5.00', number_format($necessidade['necessidade_um'], 2, '.', ''));
    }

    public function test_romaneio_rota_pdf_retorna_404_quando_rota_nao_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 2]],
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.rotas.romaneio-pdf', [$lote, $c['rota']]))
            ->assertNotFound();
    }

    public function test_romaneio_rota_pdf_baixa_com_nome_rota_motorista_quando_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 2]],
        ]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $c['rota']]), [
            'nome_motorista' => 'Motorista teste',
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
            'id_veiculo' => $veiculo->id,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 1,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->seedCaptacaoMovimentacao();
        $this->garantirEstoqueGalpaoLote($c, $lote->fresh());

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote->fresh(), $c['rota']]))
            ->assertOk();

        $nomeEsperado = RomaneioRotaPdfNomeArquivo::gerar('Rota Teste', 'Motorista teste');

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.rotas.romaneio-pdf', [$lote->fresh(), $c['rota']]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            $nomeEsperado,
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_romaneio_rota_pdf_view_usa_carregamento_e_bloco_por_loja(): void
    {
        $html = view('admin.captacao.pdf.romaneio-rota', [
            'romaneio' => [
                'rota_nome' => 'Rota Teste',
                'motorista_nome' => 'João',
                'lojas' => [[
                    'ordem_carregamento' => 1,
                    'cliente_nome' => 'Loja A',
                    'itens' => [[
                        'fruta_nome' => 'Banana',
                        'quantidade_um_formatado' => '2,00',
                        'caixas_formatado' => '2,00',
                        'quantidade_kg_formatado' => '20,00',
                    ]],
                    'totais_por_um' => [['quantidade_formatado' => '2,00', 'unidade_medicao' => 'CX']],
                    'total_caixas_formatado' => '2,00',
                    'total_kg_formatado' => '20,00',
                ]],
                'totais_gerais' => [
                    'totais_por_um' => [['quantidade_formatado' => '2,00', 'unidade_medicao' => 'CX']],
                    'total_caixas_formatado' => '2,00',
                    'total_kg_formatado' => '20,00',
                ],
            ],
            'lote' => tap(new \App\Models\Captacao\CaptacaoLote(), function ($lote): void {
                $lote->data_referencia = now();
            }),
            'veiculoNome' => 'Caminhão',
            'geradoEm' => now(),
            'logoDataUri' => null,
            'cores' => [
                'azul' => '#1A5FB4',
                'amarelo' => '#FBC02D',
                'verde' => '#2E7D32',
            ],
        ])->render();

        $this->assertStringContainsString('<td class="text-right">Total geral</td>', $html);
        $this->assertStringNotContainsString('<th class="col-carregamento">Ordem</th>', $html);
        $this->assertStringContainsString('class="loja-bloco"', $html);
        $this->assertStringContainsString('romaneio-loja', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString('rowspan=', $html);
        $this->assertStringContainsString('class="col-cliente"', $html);
    }

    public function test_matriz_exibe_botao_romaneio_pdf_na_aba_rota_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [['id_fruta' => $c['fruta']->id, 'quantidade' => 2]],
        ]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $c['rota']]), [
            'nome_motorista' => 'Motorista teste',
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
            'id_veiculo' => $veiculo->id,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 1,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->seedCaptacaoMovimentacao();
        $this->garantirEstoqueGalpaoLote($c, $lote->fresh());

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote->fresh(), $c['rota']]))
            ->assertOk();

        $urlPdf = route('admin.captacao.lotes.rotas.romaneio-pdf', [$lote->fresh(), $c['rota']]);

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rota-'.$c['rota']->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($urlPdf, $html);
        $this->assertStringContainsString('ri-file-download-line', $html);
    }
}
