<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\PedidoOrigem;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Estoque;
use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\PedidoCaptacaoEstadoService;
use App\Services\Captacao\PedidoService;

class CaptacaoPedidoPorLojaTest extends CaptacaoTestCase
{
    public function test_carteiras_lista_lote_em_andamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.carteiras', [
                'data_referencia' => $lote->data_referencia->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee($c['carteira']->nome, false)
            ->assertSee('Abrir captação do dia', false)
            ->assertSee('Criar captação', false);
    }

    public function test_modulo_captacao_exibe_topbar_criar_captacao_em_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->withSession(['app_modulo' => 'captacao'])
            ->get(route('admin.captacao.pedidos-por-loja.lojas', $lote))
            ->assertOk()
            ->assertSee('Criar Captação', false)
            ->assertSee('Módulos', false)
            ->assertSee('modal-criar-captacao', false)
            ->assertSee('Buscar loja', false)
            ->assertSee('filtro-lojas-captacao', false);
    }

    public function test_abrir_captacao_no_modulo_redireciona_para_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $this->actingAs($user)
            ->withSession(['app_modulo' => 'captacao'])
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => now()->toDateString(),
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect(route('admin.captacao.pedidos-por-loja.lojas', CaptacaoLote::query()->latest('id')->first()));
    }

    public function test_lista_todas_lojas_da_carteira_mesmo_sem_frutas_vinculadas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $semFruta = \App\Models\Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'LOJA SEM FRUTA POR LOJA TESTE',
        ]);

        $lote = $this->criarLoteCaptacao($c);

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.lojas', $lote));

        $response->assertOk()
            ->assertViewHas('lojas', function ($lojas) use ($semFruta): bool {
                return $lojas->contains(fn (array $entrada): bool => (int) $entrada['cliente']->id === (int) $semFruta->id
                    && $entrada['possui_frutas'] === false);
            });
    }

    public function test_show_lista_frutas_vinculadas_mesmo_sem_pedido_no_lote(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $c['fruta']->update([
            'unidade_medicao' => 'CX',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'preco_medio_kg' => '4.0000',
            'preco_medio_um' => '50.0000',
            'qtd_fruta_kg' => '100',
            'qtd_fruta_um' => '10',
            'ativo_unico' => 1,
        ]);

        $c['cliente']->update(['percentual_margem_alvo' => 25]);

        $this->assertFalse(
            Pedido::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('id_cliente', $c['cliente']->id)
                ->exists(),
        );

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertViewHas('linhas', function ($linhas) use ($c): bool {
                $linha = $linhas->first();

                return $linhas->count() === 1
                    && (int) $linha['fruta']->id === (int) $c['fruta']->id
                    && $linha['custo'] === '50.0000'
                    && $linha['item_atual'] === null;
            })
            ->assertSee($c['fruta']->nome, false);
    }

    public function test_show_exibe_ultimo_pedido_no_topo(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $loteHoje = $this->criarLoteCaptacao($c, '2026-06-01');
        $loteOntem = $this->criarLoteCaptacao($c, '2026-05-31');

        $pedidoOntem = Pedido::query()->create([
            'id_captacao_lote' => $loteOntem->id,
            'id_cliente' => $c['cliente']->id,
            'origem' => PedidoOrigem::Web,
            'captacao_concluida' => true,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedidoOntem->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '7',
            'preco_venda' => '12.50',
            'custo_referencia' => '8.0000',
            'version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$loteHoje, $c['cliente']]))
            ->assertOk()
            ->assertSee('Último pedido', false)
            ->assertSee('31/05/2026', false)
            ->assertSee('12,50', false)
            ->assertSee('Rent.', false)
            ->assertSee('Rent.%', false)
            ->assertViewHas('rentabilidadeUltimoPedido', fn (?array $rent): bool => $rent !== null
                && $rent['margem_percentual'] === '36.00')
            ->assertViewHas('linhasUltimoPedido', fn ($linhas): bool => $linhas->count() === 1
                && $linhas->first()['rentabilidade']['margem_percentual'] === '36.00');
    }

    public function test_show_rentabilidade_considera_desconto_nf_do_cliente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $c['cliente']->update(['desconto_nf' => 10]);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $loteHoje = $this->criarLoteCaptacao($c, '2026-06-01');
        $loteOntem = $this->criarLoteCaptacao($c, '2026-05-31');

        $pedidoOntem = Pedido::query()->create([
            'id_captacao_lote' => $loteOntem->id,
            'id_cliente' => $c['cliente']->id,
            'origem' => PedidoOrigem::Web,
            'captacao_concluida' => true,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedidoOntem->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '7',
            'preco_venda' => '12.50',
            'custo_referencia' => '8.0000',
            'version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$loteHoje, $c['cliente']]))
            ->assertOk()
            ->assertSee('Desc. NF 10,00%', false)
            ->assertViewHas('rentabilidadeUltimoPedido', fn (?array $rent): bool => $rent !== null
                && $rent['margem_percentual'] === '28.89'
                && $rent['faturamento'] === '78.75')
            ->assertViewHas('linhasUltimoPedido', fn ($linhas): bool => $linhas->count() === 1
                && $linhas->first()['rentabilidade']['margem_percentual'] === '28.89');
    }

    public function test_concluir_loja_exige_quantidade(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => true,
            ])
            ->assertStatus(422);
    }

    public function test_lista_loja_concluida_sem_rentabilidade_exibe_concluido(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'origem' => PedidoOrigem::Web,
            'captacao_concluida' => true,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '5',
            'preco_venda' => '10.00',
            'custo_referencia' => null,
            'version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.lojas', $lote))
            ->assertOk()
            ->assertSee('Concluído', false)
            ->assertDontSee('Não iniciado', false);
    }

    public function test_salvar_pedido_com_itens_reutiliza_mesmo_pedido_em_chamadas_consecutivas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $lote = $this->criarLoteCaptacao($c);

        $payload = [
            'id_cliente' => $c['cliente']->id,
            'itens' => [
                [
                    'id_fruta' => $c['fruta']->id,
                    'quantidade' => '2',
                    'preco_venda' => '10.00',
                ],
            ],
        ];

        $service = app(PedidoService::class);
        $primeiro = $service->salvarPedidoComItens($lote, $payload, PedidoOrigem::Web, $user);
        $segundo = $service->salvarPedidoComItens($lote, $payload, PedidoOrigem::Web, $user);

        $this->assertSame($primeiro->id, $segundo->id);
        $this->assertSame(
            1,
            Pedido::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('id_cliente', $c['cliente']->id)
                ->count(),
        );
    }

    public function test_salvar_pedido_via_json_para_autosave(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'preco_medio_kg' => '5.0000',
            'qtd_fruta_kg' => '100',
            'ativo_unico' => 1,
        ]);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '3',
                        'preco_venda' => '9.50',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '3.000',
            'preco_venda' => '9.5000',
        ]);
    }

    public function test_concluir_loja_com_quantidade(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'preco_medio_kg' => '5.0000',
            'qtd_fruta_kg' => '100',
            'ativo_unico' => 1,
        ]);

        $c['cliente']->update(['percentual_margem_alvo' => 30]);

        $this->actingAs($user)
            ->put(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '10',
                        'preco_venda' => '8.50',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => true,
            ])
            ->assertRedirect();

        $this->assertTrue(
            Pedido::query()
                ->where('id_captacao_lote', $lote->id)
                ->where('id_cliente', $c['cliente']->id)
                ->where('captacao_concluida', true)
                ->exists(),
        );

        $estado = app(PedidoCaptacaoEstadoService::class)->estadoLoja(
            $lote->fresh(['pedidos.itens']),
            $c['cliente']->fresh(),
        );
        $this->assertSame(PedidoCaptacaoEstadoService::ESTADO_CONCLUIDO, $estado['estado']);
    }

    public function test_reabrir_editar_e_finalizar_persiste_quantidade_e_preco(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'preco_medio_kg' => '5.0000',
            'qtd_fruta_kg' => '100',
            'ativo_unico' => 1,
        ]);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '10',
                        'preco_venda' => '8.50',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '15',
                        'preco_venda' => '12.75',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '15.000',
            'preco_venda' => '12.7500',
        ]);
    }

    public function test_salvar_pedido_com_quantidade_vazia_remove_item(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '4',
                        'preco_venda' => '7.00',
                    ],
                ],
            ])
            ->assertOk();

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $c['cliente']->id)
            ->firstOrFail();

        $this->assertDatabaseHas('pedido_itens', [
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '4.000',
        ]);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '',
                        'preco_venda' => '7.00',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('pedido_itens', [
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
        ]);
    }

    public function test_pedido_concluido_bloqueia_edicao_ate_reabrir(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '5',
                        'preco_venda' => '9.00',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertViewHas('podeEditar', false)
            ->assertViewHas('pedidoConcluido', true)
            ->assertSee('Reabrir pedido', false)
            ->assertSee('Pedido finalizado', false);

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '8',
                        'preco_venda' => '11.00',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pedido']);

        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '5.000',
            'preco_venda' => '9.0000',
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->putJson(route('admin.captacao.pedidos-por-loja.salvar', [$lote, $c['cliente']]), [
                'itens' => [
                    [
                        'id_fruta' => $c['fruta']->id,
                        'quantidade' => '8',
                        'preco_venda' => '11.00',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '8.000',
            'preco_venda' => '11.0000',
        ]);
    }

    public function test_show_exibe_opcoes_saida_fisica_galpao_e_hubs(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = UnidadeNegocio::factory()->create([
            'nome' => 'HUB TESTE PEDIDO LOJA',
            'is_hub' => true,
            'possui_estoque' => true,
            'emite_nota_fiscal' => false,
            'status' => true,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertSee('Saída do estoque físico:', false)
            ->assertSee('|', false)
            ->assertSee('HUB TESTE PEDIDO LOJA', false)
            ->assertViewHas('idSaidaSelecionada', $c['galpao']->id);
    }

    public function test_show_exibe_campo_numero_pedido_editavel(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertSee('Nº pedido', false)
            ->assertSee('id="numero-pedido-loja"', false);
    }

    public function test_pedido_por_loja_salva_numero_pedido(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $c['cliente']]), [
                'numero_pedido' => 'PL-300',
            ])
            ->assertOk()
            ->assertJsonPath('numero_pedido', 'PL-300');

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'numero_pedido' => 'PL-300',
        ]);
    }

    public function test_pedido_por_loja_bloqueia_numero_pedido_quando_concluido(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'origem' => PedidoOrigem::Web,
            'captacao_concluida' => true,
            'numero_pedido' => 'ORIGINAL',
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertViewHas('pedidoConcluido', true)
            ->assertViewHas('podeEditar', false)
            ->assertSee('ORIGINAL', false);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $c['cliente']]), [
                'numero_pedido' => 'NOVO',
            ])
            ->assertUnprocessable();

        $this->assertSame('ORIGINAL', $pedido->fresh()->numero_pedido);
    }

    public function test_show_custo_usa_estoque_hub_mais_co_faturamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = UnidadeNegocio::factory()->create([
            'is_hub' => true,
            'possui_estoque' => true,
            'emite_nota_fiscal' => false,
            'status' => true,
        ]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'custo_operacional' => '2.00',
            'status_position' => true,
        ]);

        $c['fruta']->update([
            'unidade_medicao' => 'CX',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $hub->id,
            'id_fruta' => $c['fruta']->id,
            'preco_medio_um' => '50.0000',
            'preco_medio_kg' => '5.0000',
            'qtd_fruta_um' => '10',
            'qtd_fruta_kg' => '100',
            'ativo_unico' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.pedidos-por-loja.saida-fisica-venda', [$lote, $c['cliente']]), [
                'id_unidade_negocio_saida_venda' => $hub->id,
            ])
            ->assertOk()
            ->assertJsonPath('custos.'.$c['fruta']->id.'.pm', '50.00')
            ->assertJsonPath('custos.'.$c['fruta']->id.'.co', '20.00')
            ->assertJsonPath('custos.'.$c['fruta']->id.'.final', '70.00');

        $this->actingAs($user)
            ->get(route('admin.captacao.pedidos-por-loja.show', [$lote, $c['cliente']]))
            ->assertOk()
            ->assertSee('PM saída', false)
            ->assertSee('CO fatur.', false)
            ->assertSee('50,00', false)
            ->assertSee('20,00', false)
            ->assertSee('70,00', false);
    }

    public function test_patch_saida_fisica_grava_no_pedido_sem_alterar_cadastro_cliente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = UnidadeNegocio::factory()->create([
            'nome' => 'HUB OVERRIDE LOJA',
            'is_hub' => true,
            'possui_estoque' => true,
            'emite_nota_fiscal' => false,
            'status' => true,
        ]);

        $padraoCadastro = (int) $c['cliente']->id_unidade_negocio_saida_fisico_padrao;
        $this->assertSame((int) $c['galpao']->id, $padraoCadastro);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.pedidos-por-loja.saida-fisica-venda', [$lote, $c['cliente']]), [
                'id_unidade_negocio_saida_venda' => $hub->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_unidade_negocio_saida_venda', $hub->id);

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $c['cliente']->id)
            ->first();

        $this->assertNotNull($pedido);
        $this->assertSame($hub->id, (int) $pedido->id_unidade_negocio_saida_venda);
        $this->assertSame($padraoCadastro, (int) $c['cliente']->fresh()->id_unidade_negocio_saida_fisico_padrao);
    }

    public function test_saida_override_nao_vaza_para_outro_lote(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = UnidadeNegocio::factory()->create([
            'is_hub' => true,
            'possui_estoque' => true,
            'emite_nota_fiscal' => false,
            'status' => true,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);
        $lote1 = $this->criarLoteCaptacao($c, '2026-06-01');
        $lote2 = $this->criarLoteCaptacao($c, '2026-06-02');

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.pedidos-por-loja.saida-fisica-venda', [$lote1, $c['cliente']]), [
                'id_unidade_negocio_saida_venda' => $hub->id,
            ])
            ->assertOk();

        $pedidoLote2 = Pedido::query()
            ->where('id_captacao_lote', $lote2->id)
            ->where('id_cliente', $c['cliente']->id)
            ->first();

        $this->assertNull($pedidoLote2?->id_unidade_negocio_saida_venda);
    }

    public function test_finalizar_faturamento_bloqueia_com_loja_pendente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);
        $lote = $this->criarLoteCaptacao($c, '2026-06-20');

        app(\App\Services\Captacao\PedidoService::class)->adicionarLojaNaMatriz(
            $lote,
            $c['cliente'],
            \App\Enums\PedidoOrigem::Web,
            $user,
        );

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 2,
        ])->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-06-20',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(
            CaptacaoLoteStatus::CaptacaoEmAndamento,
            $lote->fresh()->status,
        );
    }
}
