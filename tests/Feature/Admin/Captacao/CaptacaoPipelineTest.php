<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\ClienteFrutaVinculoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CaptacaoPipelineTest extends CaptacaoTestCase
{
    public function test_fluxo_lucas_jefferson_transiciona_status(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaCiganIniciada, $lote->status);

        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'possui_estoque' => true]);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        Storage::fake('local');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf-128286.xml', 50, 'application/xml'),
            ])
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'saida-estoque-fisico']))
            ->assertSessionHas('success');

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::SaidaEstoqueFisico, $lote->status);
        $this->assertTrue($lote->possuiNfTransferencia());

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-saida-estoque-fisico', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::AguardandoVinculoFrete, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaFinalizada, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::FaturamentoCiganIniciado, $lote->status);

        $this->enviarNfVendaPipeline($user, $lote);

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VincularRotasNosPedidos, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-vinculo-rotas', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VincularFreteVenda, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-frete-venda', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VendasFinalizadas, $lote->status);
        $this->assertTrue($lote->possuiNfVenda());
    }

    public function test_concluir_vinculo_rotas_bloqueia_pedido_com_quantidade_sem_rota(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::VincularRotasNosPedidos,
        ]);

        $pedido = \App\Models\Captacao\Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'captacao_concluida' => true,
            'origem' => \App\Enums\PedidoOrigem::Web,
        ]);

        \App\Models\Captacao\PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '12.50',
            'version' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)
            ->from(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->post(route('admin.captacao.lotes.pipeline.concluir-vinculo-rotas', $lote))
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertSessionHasErrors('pedidos');

        $this->assertSame(CaptacaoLoteStatus::VincularRotasNosPedidos, $lote->fresh()->status);
    }

    public function test_concluir_vinculo_rotas_bloqueia_sem_ordem_carregamento(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::VincularRotasNosPedidos,
        ]);

        $pedido = \App\Models\Captacao\Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'ordem_carregamento' => null,
            'captacao_concluida' => true,
            'origem' => \App\Enums\PedidoOrigem::Web,
        ]);

        \App\Models\Captacao\PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '12.50',
            'version' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)
            ->from(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->post(route('admin.captacao.lotes.pipeline.concluir-vinculo-rotas', $lote))
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertSessionHasErrors('pedidos');

        $this->assertSame(CaptacaoLoteStatus::VincularRotasNosPedidos, $lote->fresh()->status);
    }

    public function test_upload_nf_venda_sem_rota_movimenta_e_aguarda_vinculo_rotas(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::FaturamentoCiganIniciado,
        ]);

        $pedido = \App\Models\Captacao\Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'id_unidade_negocio_saida_venda' => $c['galpao']->id,
            'captacao_concluida' => true,
            'origem' => \App\Enums\PedidoOrigem::Web,
        ]);

        \App\Models\Captacao\PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '12.50',
            'version' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-venda-cigan.upload', $lote), [
            'arquivo_nf_venda' => UploadedFile::fake()->create('nf-venda.xml', 50, 'application/xml'),
        ])->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']));

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VincularRotasNosPedidos, $lote->status);
        $this->assertTrue($lote->possuiNfVenda());
        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
        ]);
    }

    public function test_matriz_exibe_aba_arquivo_cigan_na_transferencia_iniciada(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee('Arquivo Cigam', false)
            ->assertSee('matriz-tab-arquivo-cigan', false)
            ->assertSee('Baixar arquivo Cigam', false);
    }

    public function test_download_arquivo_cigan_exige_hub_origem(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote))
            ->assertRedirect()
            ->assertSessionHasErrors('id_unidade_negocio_hub_origem');
    }

    public function test_download_arquivo_cigan_transferencia_txt_layout_edi(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        $c = $this->cenarioCaptacaoBasico();
        $c['faturamento']->update([
            'id_cigam' => '881001',
            'id_cliente' => $c['cliente']->id,
        ]);
        $c['cliente']->update(['id_cigam' => '770099']);
        $c['galpao']->update(['id_cigam' => '882002']);
        $c['fruta']->update(['id_cigam' => '000042']);
        $hub = $this->criarHubComEstoque($c['fruta']);
        $hub->update(['id_cigam' => '883003']);
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $c['cliente']->id,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => 10,
        ])->assertOk();

        $lote->update([
            'status' => CaptacaoLoteStatus::TransferenciaCiganIniciada,
            'id_unidade_negocio_hub_origem' => $hub->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=ISO-8859-1');

        $conteudo = $response->streamedContent();
        $linhas = array_values(array_filter(explode("\n", rtrim($conteudo, "\r\n"))));

        $this->assertGreaterThanOrEqual(2, count($linhas));
        $this->assertSame(688, strlen($linhas[0]));
        $this->assertSame('N', $linhas[0][0]);

        $gerador = app(\App\Services\Captacao\CiganEdiNfTransferenciaGerador::class);

        $serieNumero = $gerador->serieENumeroNotaFiscalCigam('883003');
        $this->assertSame($serieNumero['serie'], substr($linhas[0], 2, 5), 'Série (pos. 3–7) = NF + id_cigam HUB sem zeros à esquerda');
        $this->assertSame($serieNumero['numero'], substr($linhas[0], 8, 7), 'Número NF (pos. 9–15) = continuação quando passar de 5 caracteres');
        $this->assertSame('NF883', substr($linhas[0], 2, 5));
        $this->assertSame('003    ', substr($linhas[0], 8, 7));
        $this->assertSame('10062026', substr($linhas[0], 25, 8), 'Data emissão nas pos. 26–33 = dia atual');
        $this->assertSame('10062026', substr($linhas[0], 34, 8), 'Data entrada nas pos. 35–42 = dia atual');
        $this->assertSame('5152A', substr($linhas[0], 19, 5), 'Tipo de operação (pos. 20–24) = 5152A');
        $this->assertSame('5152A', substr($linhas[1], 371, 5), 'Tipo de operação (pos. 372–376) = 5152A');

        $this->assertSame('770099', substr($linhas[0], 51, 6), 'Cliente (pos. 52–57) = id_cigam do cliente da UN');
        $this->assertSame('770099', substr($linhas[0], 58, 6), 'Cobrança (pos. 59–64) = mesmo código do cliente');
        $this->assertSame('000488', substr($linhas[0], 131, 6), 'Transportadora fixa (pos. 132–137)');
        $this->assertNotSame('881001', substr($linhas[0], 51, 6), 'Código da UN de faturamento não é cliente/cobrança');
        $this->assertSame('R', substr($linhas[0], 43, 1), 'Via transporte (pos. 44)');
        $this->assertSame('1', substr($linhas[0], 265, 1), 'Tipo frete (pos. 266)');
        $this->assertSame('S', substr($linhas[0], 282, 1), 'Entrada/Saída (pos. 283) = saída');
        $this->assertSame(str_repeat(' ', 3), substr($linhas[0], 315, 3), 'Condição pagamento (pos. 316–318) em branco');
        $this->assertNotSame('', trim(substr($linhas[0], 321, 60)), 'Nome cliente (pos. 322–381)');
        $this->assertSame(str_repeat(' ', 30), substr($linhas[0], 382, 30), 'Contato (pos. 383–412) em branco');
        $this->assertSame(str_repeat(' ', 20), substr($linhas[0], 413, 20), 'Fone (pos. 414–433) em branco');
        $this->assertSame(str_repeat(' ', 40), substr($linhas[0], 455, 40), 'Endereço (pos. 456–495) em branco');
        $this->assertSame(str_repeat(' ', 20), substr($linhas[0], 496, 20), 'Bairro (pos. 497–516) em branco');
        $this->assertSame(str_repeat(' ', 30), substr($linhas[0], 517, 30), 'Cidade (pos. 518–547) em branco');
        $this->assertSame(
            $gerador->ufClienteCigam($c['cliente']->fresh(['unidadeNegocio.estado'])),
            substr($linhas[0], 548, 2),
            'UF (pos. 549–550) da unidade do cliente',
        );
        $this->assertSame(str_repeat(' ', 8), substr($linhas[0], 551, 8), 'CEP (pos. 552–559) em branco');
        $this->assertNotSame('', trim(substr($linhas[0], 560, 14)), 'CNPJ (pos. 561–574)');
        $this->assertSame(str_repeat(' ', 20), substr($linhas[0], 575, 20), 'Inscrição estadual (pos. 576–595) em branco');
        $this->assertContains(substr($linhas[0], 596, 1), ['F', 'J'], 'Pessoa F/J (pos. 597)');
        $this->assertSame('I', $linhas[1][0]);
        $this->assertSame(719, strlen($linhas[1]));
        $this->assertSame(
            $gerador->codigoMaterialCigam('000042'),
            substr($linhas[1], 2, 20),
            'Código material (pos. 3–22) = 14 espaços + 6 dígitos finais do id_cigam da fruta',
        );
        $this->assertSame(
            $gerador->codigoUnidadeNegocioCigam('883003', 'unidade HUB de origem'),
            substr($linhas[0], 601, 3),
            'Unidade negócio (pos. 602–604) = id_cigam do HUB de origem',
        );
        $this->assertSame('001', substr($linhas[0], 604, 3), 'Centro armazenagem (pos. 605–607) do HUB');
        $this->assertSame('S', substr($linhas[0], 607, 1), 'Espécie estoque (pos. 608) = S');
        $this->assertSame(
            $gerador->codigoUnidadeNegocioCigam('883003', 'unidade HUB de origem'),
            substr($linhas[1], 655, 3),
            'Unidade negócio no item (pos. 656–658) = HUB de origem',
        );
        $this->assertSame(str_repeat(' ', 20), substr($linhas[1], 658, 20), 'Centro/separador no item (pos. 659–678) em branco — só no N');
        $this->assertSame('S', substr($linhas[1], 678, 1), 'Espécie estoque no item (pos. 679) = S');
        $this->assertSame(str_repeat(' ', 5), substr($linhas[1], 680, 5), 'Sequência item (pos. 681–685) em branco');
        $this->assertSame(' ', substr($linhas[1], 38, 1), 'Separador entre quantidade e peças (pos. 39)');
        $this->assertSame(str_repeat(' ', 14), substr($linhas[1], 39, 14), 'Peças (pos. 40–53) em branco');
        $this->assertSame(
            $gerador->formatarQuantidadeUmCigam(5.0),
            substr($linhas[1], 23, 15),
            'Quantidade UM (pos. 24–38) máscara N8.6 (× 1.000.000)',
        );
        $this->assertStringEndsWith(
            '000000',
            substr($linhas[1], 23, 15),
            'Quantidade deve terminar com 6 zeros decimais implícitos (N8.6)',
        );
        $this->assertSame(
            str_repeat(' ', 15),
            substr($linhas[1], 55, 15),
            'Preço unitário (pos. 56–70) em branco',
        );
        $this->assertNotSame('000000000000000', substr($linhas[1], 55, 15), 'Preço não deve ser zeros');
        $this->assertNotSame('', trim(substr($linhas[1], 114, 200)), 'Descrição item (pos. 115–314)');
        $this->assertSame('0', substr($linhas[1], 94, 1), 'IPI (pos. 95) = não considera');
        $this->assertStringContainsString(
            'cigan-transferencia-lote-'.$lote->id.'.txt',
            (string) $response->headers->get('content-disposition'),
        );

        Carbon::setTestNow();
    }

    public function test_download_arquivo_cigan_usa_id_cigam_apenas_das_frutas_do_lote(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $c['faturamento']->update(['id_cliente' => $c['cliente']->id]);
        $c['cliente']->update(['id_cigam' => '770099']);

        $frutaLoteA = $c['fruta'];
        $frutaLoteA->update(['id_cigam' => '001111', 'nome' => 'FRUTA LOTE A']);

        $frutaLoteB = Fruta::factory()->create(['id_cigam' => '002222', 'nome' => 'FRUTA LOTE B']);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($c['cliente'], [$frutaLoteA->id, $frutaLoteB->id]);

        $hub = $this->criarHubComEstoque($frutaLoteA);
        $hub->update(['id_cigam' => '883003']);

        $loteA = $this->criarLoteCaptacao($c, '2026-05-27');
        $loteB = $this->criarLoteCaptacao($c, '2026-05-28');

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        foreach ([[$loteA, $frutaLoteA], [$loteB, $frutaLoteB]] as [$lote, $fruta]) {
            $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
            ])->assertOk();

            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $fruta->id,
                'quantidade' => 10,
                'preco_venda' => 12,
            ])->assertOk();

            $lote->update([
                'status' => CaptacaoLoteStatus::TransferenciaCiganIniciada,
                'id_unidade_negocio_hub_origem' => $hub->id,
            ]);
        }

        $gerador = app(\App\Services\Captacao\CiganEdiNfTransferenciaGerador::class);
        $materialA = $gerador->codigoMaterialCigam('001111');
        $materialB = $gerador->codigoMaterialCigam('002222');

        $conteudoB = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $loteB))
            ->assertOk()
            ->streamedContent();

        $linhaItemB = array_values(array_filter(explode("\n", rtrim($conteudoB, "\r\n"))))[1];

        $this->assertStringContainsString($materialB, $linhaItemB);
        $this->assertStringNotContainsString($materialA, $conteudoB);
    }

    public function test_definir_hub_origem_cigan_na_matriz(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->put(route('admin.captacao.lotes.hub-origem-cigan.update', $lote), [
                'id_unidade_negocio_hub_origem' => $hub->id,
            ])
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertSessionHas('success');

        $this->assertSame($hub->id, $lote->fresh()->id_unidade_negocio_hub_origem);
    }

    public function test_upload_nf_transferencia_disponibiliza_download_e_muda_status(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'possui_estoque' => true]);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update([
            'status' => CaptacaoLoteStatus::TransferenciaCiganIniciada,
            'id_unidade_negocio_hub_origem' => $hub->id,
        ]);

        Storage::fake('local');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf-transferencia.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'saida-estoque-fisico']))
            ->assertSessionHas('success');

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::SaidaEstoqueFisico, $lote->status);
        $this->assertSame('nf-transferencia.pdf', $lote->arquivo_nf_transferencia_nome);
        Storage::disk('local')->assertExists($lote->arquivo_nf_transferencia_path);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.nf-transferencia-cigan.download', $lote))
            ->assertOk();
    }

    public function test_upload_nf_exige_hub_origem_salvo(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        Storage::fake('local');

        $this->actingAs($user)
            ->from(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('id_unidade_negocio_hub_origem');
    }

    public function test_matriz_exibe_aba_arquivo_cigan_aguardando_vinculo_frete(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update([
            'status' => CaptacaoLoteStatus::AguardandoVinculoFrete,
            'arquivo_nf_transferencia_path' => 'captacao/cigan/nf-transferencia/lote-test.xml',
            'arquivo_nf_transferencia_nome' => 'nf-test.xml',
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertOk()
            ->assertSee('Baixar NF enviada', false)
            ->assertDontSee('Enviar NF e avançar', false);
    }

    public function test_upload_nf_venda_efetiva_movimentacoes_e_avanca_status(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 5, '12.50');
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);
        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ]);
        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote->fresh()));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote->fresh()));

        $this->enviarNfVendaPipeline($user, $lote->fresh());

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VincularRotasNosPedidos, $lote->status);
        $this->assertTrue($lote->possuiNfVenda());

        $this->concluirPipelineAteVendasFinalizadas($user, $lote, $c);

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VendasFinalizadas, $lote->status);

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
        ]);
    }

    public function test_download_arquivo_cigan_vendas_apos_iniciar_faturamento(): void
    {
        $this->seedCaptacaoMovimentacao();
        $this->seed(\Database\Seeders\EstadoSeeder::class);

        $c = $this->cenarioCaptacaoBasico();
        $faturamento = $c['faturamento'];
        $faturamento->update(['id_cigam' => '881001', 'centro_armazenagem' => '001']);

        $c['cliente']->update([
            'id_cigam' => '770099',
            'cnpj_cpf' => '12345678000199',
        ]);
        $unidadeLoja = UnidadeNegocio::factory()->create([
            'id_estado' => \App\Models\Estado::query()->firstOrFail()->id,
        ]);
        $c['cliente']->update(['id_unidade_negocio' => $unidadeLoja->id]);

        $c['fruta']->update(['id_cigam' => '990011']);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaFinalizada]);

        $pedido = \App\Models\Captacao\Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'captacao_concluida' => true,
            'origem' => \App\Enums\PedidoOrigem::Web,
        ]);

        \App\Models\Captacao\PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 3,
            'preco_venda' => '10.00',
            'version' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote))
            ->assertRedirect();

        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'possui_estoque' => true, 'nome' => 'HUB MV TESTE']);
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertOk()
            ->assertSee('Saída física', false)
            ->assertSee('HUB MV TESTE', false)
            ->assertSee($c['galpao']->nome, false);

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-vendas', $lote));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=ISO-8859-1');
        $conteudo = $response->streamedContent();
        $this->assertStringStartsWith('N', $conteudo);

        $linhas = array_values(array_filter(explode("\n", trim($conteudo))));
        $gerador = app(\App\Services\Captacao\CiganEdiNfTransferenciaGerador::class);
        $linhaI = collect($linhas)->first(fn (string $l) => str_starts_with($l, 'I'));
        $this->assertNotNull($linhaI);
        $this->assertSame(
            $gerador->formatarPrecoUnitarioCigam(10.0),
            substr($linhaI, 55, 15),
            'Preço unitário vendas (pos. 56–70) = preco_venda da matriz',
        );
    }

    public function test_download_arquivo_cigan_transferencia_indisponivel_fora_da_fase(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote))
            ->assertNotFound();
    }
}
