<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class VendaMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_create_carrega_origem_cliente_e_unidade_faturamento_sem_erro(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $html = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CRIAR]))
            ->get(route('admin.movimentacoes.vendas.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($c['unidade']->nome, (string) $html);
        $this->assertStringContainsString($c['empresa_cliente']->nomeExibicao(), (string) $html);
        $this->assertStringContainsString($c['unidade_faturamento']->nome, (string) $html);
    }

    public function test_usuario_com_permissao_cria_venda_multi_item_e_calcula_estoque_valores_e_resultado(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $fruta2 = Fruta::factory()->create([
            'kg_por_unidade_medicao' => 5,
            'icms_na_compra' => 0,
            'icms_ex_compra' => 0,
            'um_icms' => FrutaUmIcms::KG->value,
        ]);

        $this->registrarCompra($c, '10', '500,00');
        $this->registrarCompra(array_merge($c, ['fruta' => $fruta2]), '4', '200,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => ' NF-100 ',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'id_unidade_negocio_faturamento' => $c['unidade_faturamento']->id,
            'data_emissao' => '2026-05-16 10:00:00',
            'observacao' => 'Venda com dois itens.',
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
                ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '1', 'valor_nf_total' => '80,00'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('vendas_notas', [
            'numero_nf' => 'NF-100',
            'valor_total_nf' => '380.00',
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
        ]);

        $vendas = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $vendas);
        $venda = $vendas->firstWhere('id_fruta', $c['fruta']->id);
        $this->assertNotNull($venda);
        $this->assertSame('300.00', (string) $venda->valor_nf_total);
        $this->assertSame('150.00', (string) $venda->valor_nf_um);
        $this->assertSame('15.00', (string) $venda->valor_nf_kg);
        $this->assertSame('100.00', (string) $venda->valor_custo_saida);
        $this->assertSame('100.00', (string) $venda->valor_total_movimentacao);
        $this->assertSame('200.00', (string) $venda->resultado_movimentacao);
        $this->assertSame('5.00', (string) $venda->preco_medio_fruta_kg);
        $this->assertSame($c['unidade_faturamento']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertNull($venda->categoria_descarte_id);
        $this->assertSame('0.00', (string) $venda->valor_icms_total);
        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertDatabaseHas('movimentacao_historicos', [
            'movimentacao_cadeia_raiz_id' => $venda->id,
            'origem' => MovimentacaoHistorico::ORIGEM_VENDA,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_VENDA,
        ]);
    }

    public function test_venda_permite_estoque_negativo_e_preserva_preco_medio(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '1', '50,00');
        $estoqueAntes = Estoque::query()
            ->where('id_unidade_negocio', $c['unidade']->id)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();

        $venda = $this->registrarVenda($c, '1000.00', '900,00');
        $estoqueDepois = Estoque::query()
            ->where('id_unidade_negocio', $c['unidade']->id)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();

        $this->assertLessThan(0, (float) $venda->saldo_estoque_fruta_kg);
        $this->assertLessThan(0, (float) $venda->saldo_estoque_fruta_um);
        $this->assertSame((string) $estoqueAntes->preco_medio_kg, (string) $venda->preco_medio_fruta_kg);
        $this->assertSame((string) $estoqueAntes->preco_medio_kg, (string) $estoqueDepois->preco_medio_kg);
        $this->assertSame(number_format((float) $venda->qtd_fruta_kg * (float) $estoqueAntes->preco_medio_kg, 2, '.', ''), (string) $venda->valor_custo_saida);
    }

    public function test_vendas_no_mesmo_frete_rateiam_apenas_ativas_e_atualizam_resultado(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $frete = Frete::factory()->create(['valor' => '100.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]);

        $v1 = $this->registrarVenda($c, '2', '300,00', $frete);
        $v2 = $this->registrarVenda($c, '3', '450,00', $frete);

        $this->assertSame('2.00', (string) $v1->fresh()->valor_frete_kg);
        $this->assertSame('40.00', (string) $v1->fresh()->valor_frete_rateio);
        $this->assertSame('160.00', (string) $v1->fresh()->resultado_movimentacao);
        $this->assertSame('60.00', (string) $v2->fresh()->valor_frete_rateio);
        $this->assertSame('240.00', (string) $v2->fresh()->resultado_movimentacao);
        $this->assertEstoque($c['unidade'], $c['fruta'], '50.00', '5.00', '5.00', '50.00', '250.00');

        $this->cancelarVendaAdmin($v1);

        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $v1->fresh()->status_registro);
        $this->assertSame('3.33', (string) $v2->fresh()->valor_frete_kg);
        $this->assertSame('99.90', (string) $v2->fresh()->valor_frete_rateio);
        $this->assertSame('200.10', (string) $v2->fresh()->resultado_movimentacao);
    }

    public function test_correcao_de_venda_cria_nova_versao_e_substituida_nao_entra_no_calculo(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $v1 = $this->registrarVenda($c, '2', '300,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->putJson(route('admin.movimentacoes.vendas.update', $v1), [
            'numero_nf' => 'NF-200',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'id_unidade_negocio_faturamento' => $c['unidade_faturamento']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '3',
            'valor_nf_total' => '480,00',
            'motivo_substituicao' => 'Correção da quantidade.',
        ])->assertOk();

        $v1->refresh();
        $v2 = Movimentacao::query()->findOrFail((int) $v1->substituida_por_id);

        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v2->status_registro);
        $this->assertSame(2, (int) $v2->versao);
        $this->assertSame('150.00', (string) $v2->valor_custo_saida);
        $this->assertSame('330.00', (string) $v2->resultado_movimentacao);
        $this->assertSame(1, Movimentacao::query()->vigentesParaCalculo()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->count());
        $this->assertEstoque($c['unidade'], $c['fruta'], '70.00', '7.00', '5.00', '50.00', '350.00');
    }

    public function test_validacoes_permissoes_cancelamento_e_rollback_transacional(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.vendas.store'), $this->payloadVenda($c))
            ->assertForbidden();

        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'id_estado' => Estado::ID_PERNAMBUCO]);
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), array_merge($this->payloadVenda($c), [
            'id_unidade_negocio_faturamento' => $hub->id,
        ]))->assertJsonValidationErrors(['id_unidade_negocio_faturamento']);

        $venda = $this->registrarVenda($c, '2', '300,00');
        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), [
            'motivo' => 'Sem permissão.',
        ])->assertForbidden();
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), [])
            ->assertJsonValidationErrors(['motivo']);

        $estoqueAntes = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail()->replicate();
        $ultimaAntes = MovimentacaoEstoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->where('status_ultima_posicao', true)->firstOrFail();
        $historicosAntes = MovimentacaoHistorico::query()->count();

        $mock = $this->createMock(ReprocessaSaidasVendaOrigem::class);
        $mock->method('reprocessarSaidasVendaNaUnidadeOrigem')->willThrowException(new RuntimeException('Falha simulada no replay.'));
        $this->app->instance(ReprocessaSaidasVendaOrigem::class, $mock);

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), ['motivo' => 'Falha simulada.'])
            ->assertServerError();

        $venda->refresh();
        $estoqueDepois = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail();
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $venda->status_registro);
        $this->assertNull($venda->cancelada_em);
        $this->assertNull($venda->cancelada_por);
        $this->assertNull($venda->motivo_cancelamento);
        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame((string) $estoqueAntes->valor_total_acumulado, (string) $estoqueDepois->valor_total_acumulado);
        $this->assertDatabaseHas('movimentacoes_estoque', ['id' => $ultimaAntes->id, 'status_ultima_posicao' => true]);
        $this->assertSame($historicosAntes, MovimentacaoHistorico::query()->count());
    }

    public function test_origem_pode_ser_hub_e_destino_cliente_e_obrigatorio_para_rastreabilidade(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase(origemHub: true);
        $this->registrarCompra($c, '5', '250,00');

        $venda = $this->registrarVenda($c, '1', '180,00');

        $this->assertSame($c['empresa_cliente']->id, (int) $venda->id_empresa_destino);
        $this->assertSame($c['unidade_faturamento']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertNotNull($venda->venda_nota_id);
        $this->assertNotNull($venda->vendaNota?->numero_nf);

        $payload = $this->payloadVenda($c);
        unset($payload['id_empresa_destino']);
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $payload)
            ->assertJsonValidationErrors(['id_empresa_destino']);
    }

    private function seedBase(): void
    {
        $this->seed(EstadoSeeder::class);
        $this->seed(CategoriaMovimentacaoSeeder::class);
        $this->seed(StatusMovimentacaoSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function cenarioBase(bool $origemHub = false): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create();
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => $origemHub,
        ]);
        $unidadeFaturamento = UnidadeNegocio::factory()->create([
            'possui_estoque' => false,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->whereIn('id_unidade_negocio', [$unidade->id, $unidadeFaturamento->id])->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidadeFaturamento->id, 'custo_operacional' => 0, 'status_position' => true]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'unidade_faturamento' => $unidadeFaturamento,
            'fruta' => Fruta::factory()->create([
                'kg_por_unidade_medicao' => 10,
                'icms_na_compra' => 0,
                'icms_ex_compra' => 0,
                'um_icms' => FrutaUmIcms::KG->value,
            ]),
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompra(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_unidade']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $cenario['frete']->id,
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarVenda(array $cenario, string $qtdUm, string $valorNfTotal, ?Frete $frete = null): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $this->payloadVenda($cenario, $qtdUm, $valorNfTotal, $frete))->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     * @return array<string, mixed>
     */
    private function payloadVenda(array $cenario, string $qtdUm = '1', string $valorNfTotal = '100,00', ?Frete $frete = null): array
    {
        return [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $cenario['empresa_unidade']->id,
            'id_empresa_destino' => $cenario['empresa_cliente']->id,
            'id_unidade_negocio_faturamento' => $cenario['unidade_faturamento']->id,
            'id_frete' => $frete?->id,
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ];
    }

    private function cancelarVendaAdmin(Movimentacao $venda): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), ['motivo' => 'Cancelamento administrativo de venda.'])
            ->assertOk();
    }

    private function assertEstoque(UnidadeNegocio $unidade, Fruta $fruta, string $kg, string $um, string $precoKg, string $precoUm, string $valor): void
    {
        $estoque = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->firstOrFail();

        $this->assertSame($kg, (string) $estoque->qtd_fruta_kg);
        $this->assertSame($um, (string) $estoque->qtd_fruta_um);
        $this->assertSame($precoKg, (string) $estoque->preco_medio_kg);
        $this->assertSame($precoUm, (string) $estoque->preco_medio_um);
        $this->assertSame($valor, (string) $estoque->valor_total_acumulado);
    }
}
