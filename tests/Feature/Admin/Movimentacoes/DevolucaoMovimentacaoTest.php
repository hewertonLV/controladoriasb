<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEntradasDevolucaoDestino;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\TipoDevolucao;
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
use App\Services\Movimentacoes\DevolucaoMovimentacaoService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class DevolucaoMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_cria_devolucao_com_retorno_aumenta_estoque_e_usa_custo_historico_da_venda(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');

        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '2');

        $this->assertSame(CategoriaMovimentacaoTipo::Devolucao->value, (int) $devolucao->categoria_movimentacao_id);
        $this->assertSame(StatusMovimentacao::ID_ENTRADA, (int) $devolucao->status_movimentacao_id);
        $this->assertSame($venda->id, (int) $devolucao->movimentacao_venda_origem_id);
        $this->assertSame('400.00', (string) $devolucao->valor_devolucao_total);
        $this->assertSame('100.00', (string) $devolucao->valor_custo_devolucao);
        $this->assertSame('300.00', (string) $devolucao->resultado_devolucao);
        $this->assertSame('100.00', (string) $devolucao->valor_total_movimentacao);
        $this->assertSame('0.00', (string) $devolucao->valor_nf_total);
        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertDatabaseHas('movimentacao_historicos', [
            'movimentacao_cadeia_raiz_id' => $devolucao->id,
            'origem' => MovimentacaoHistorico::ORIGEM_DEVOLUCAO,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DEVOLUCAO,
        ]);
    }

    public function test_devolucao_sem_retorno_nao_altera_estoque_mas_estorna_resultado(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $estoqueAntes = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail()->replicate();
        $posicoesAntes = MovimentacaoEstoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->count();

        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::SEM_RETORNO_ESTOQUE, '2');

        $this->assertSame(StatusMovimentacao::ID_SAIDA, (int) $devolucao->status_movimentacao_id);
        $this->assertNull($devolucao->id_movimentacao_estoque_new);
        $this->assertSame('400.00', (string) $devolucao->valor_devolucao_total);
        $this->assertSame('100.00', (string) $devolucao->valor_custo_devolucao);
        $this->assertSame('-300.00', (string) $devolucao->resultado_devolucao);
        $estoqueDepois = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail();
        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame((string) $estoqueAntes->valor_total_acumulado, (string) $estoqueDepois->valor_total_acumulado);
        $this->assertSame($posicoesAntes, MovimentacaoEstoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->count());
    }

    public function test_devolucao_nao_usa_preco_medio_atual_nem_preco_de_venda_no_estoque(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $this->registrarCompra($c, '10', '1000,00');

        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '2');

        $this->assertSame('100.00', (string) $devolucao->valor_custo_devolucao);
        $this->assertSame('400.00', (string) $devolucao->valor_devolucao_total);
        $this->assertEstoque($c['unidade'], $c['fruta'], '180.00', '18.00', '7.78', '77.80', '1400.00');
    }

    public function test_nao_permite_devolver_mais_que_saldo_devolvivel_e_permissoes(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), $this->payloadDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '1'))
            ->assertForbidden();

        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), $this->payloadDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '5'))
            ->assertJsonValidationErrors(['qtd_fruta_um']);

        $this->registrarDevolucao($venda, TipoDevolucao::SEM_RETORNO_ESTOQUE, '2');
        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), $this->payloadDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '3'))
            ->assertJsonValidationErrors(['qtd_fruta_um']);
    }

    public function test_correcao_cria_nova_versao_e_substituida_nao_entra_no_saldo_devolvivel(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $d1 = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '1');

        $this->actingAs($this->movimentacoesDevolucoesUsuario())->putJson(route('admin.movimentacoes.devolucoes.update', $d1), [
            'movimentacao_venda_origem_id' => $venda->id,
            'tipo_devolucao' => TipoDevolucao::COM_RETORNO_ESTOQUE->value,
            'id_unidade_negocio_retorno' => $c['unidade']->id,
            'qtd_fruta_um' => '2',
            'numero_nf_devolucao' => 'DEV-002',
            'motivo_substituicao' => 'Correção de quantidade.',
        ])->assertOk();

        $d1->refresh();
        $d2 = Movimentacao::query()->findOrFail((int) $d1->substituida_por_id);
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $d1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $d2->status_registro);
        $this->assertSame('2.00', (string) $d2->qtd_fruta_um);
        $this->assertSame('2.00', number_format(app(DevolucaoMovimentacaoService::class)->saldoDevolvivelUm($venda->fresh()), 2, '.', ''));
    }

    public function test_cancelamento_admin_reverte_devolucao_com_retorno_e_rollback_se_replay_falhar(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '2');

        $mock = $this->createMock(ReprocessaEntradasDevolucaoDestino::class);
        $mock->method('reprocessarEntradasDevolucaoNaUnidadeDestino')->willThrowException(new RuntimeException('Falha simulada no replay.'));
        $this->app->instance(ReprocessaEntradasDevolucaoDestino::class, $mock);
        $historicosAntes = MovimentacaoHistorico::query()->count();
        $estoqueAntes = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail()->replicate();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.devolucoes.cancelar-admin', $devolucao), ['motivo' => 'Falha simulada.'])
            ->assertServerError();

        $devolucao->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $devolucao->status_registro);
        $this->assertNull($devolucao->cancelada_em);
        $this->assertSame($historicosAntes, MovimentacaoHistorico::query()->count());
        $estoqueDepois = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail();
        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);

        $this->app->forgetInstance(ReprocessaEntradasDevolucaoDestino::class);
        $this->cancelarDevolucaoAdmin($devolucao);
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $devolucao->fresh()->status_registro);
        $this->assertEstoque($c['unidade'], $c['fruta'], '60.00', '6.00', '5.00', '50.00', '300.00');
    }

    public function test_devolucao_hub_aplica_custo_operacional_da_unidade_faturamento_e_comum_nao_reaplica(): void
    {
        $this->seedBase();
        $comum = $this->cenarioBase();
        $this->registrarCompra($comum, '10', '500,00');
        $vendaComum = $this->registrarVenda($comum, '2', '400,00');
        $devComum = $this->registrarDevolucao($vendaComum, TipoDevolucao::COM_RETORNO_ESTOQUE, '1');
        $this->assertSame('0.00', (string) $devComum->valor_custo_operacional);
        $this->assertSame('50.00', (string) $devComum->valor_custo_devolucao);

        $hub = $this->cenarioBase(origemHub: true, custoFaturamento: 2.0);
        $this->registrarCompra($hub, '10', '500,00');
        $vendaHub = $this->registrarVenda($hub, '2', '400,00');
        $devHub = $this->registrarDevolucao($vendaHub, TipoDevolucao::COM_RETORNO_ESTOQUE, '1', $hub['unidade_faturamento']);

        $this->assertSame('2.00', (string) $devHub->valor_custo_operacional);
        $this->assertSame('70.00', (string) $devHub->valor_custo_devolucao);
        $this->assertEstoque($hub['unidade_faturamento'], $hub['fruta'], '10.00', '1.00', '7.00', '70.00', '70.00');
    }

    public function test_devolucao_com_retorno_usa_unidade_fisica_informada_e_hub_nao_hub_define_custo_operacional(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase(origemHub: true, custoFaturamento: 0.0);
        $retornoHub = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 9,
            'is_hub' => true,
        ]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $retornoHub->id, 'custo_operacional' => 9, 'status_position' => true]);

        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '2', '400,00');
        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '1', $retornoHub);

        $this->assertSame($retornoHub->id, (int) $devolucao->id_unidade_negocio_retorno);
        $this->assertSame('0.00', (string) $devolucao->valor_custo_operacional);
        $this->assertEstoque($retornoHub, $c['fruta'], '10.00', '1.00', '5.00', '50.00', '50.00');
    }

    public function test_saldo_devolvivel_ignora_canceladas_e_substituidas_e_helpers_da_venda(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $cancelada = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '1');
        $this->cancelarDevolucaoAdmin($cancelada);
        $d1 = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '1');

        $this->actingAs($this->movimentacoesDevolucoesUsuario())->putJson(route('admin.movimentacoes.devolucoes.update', $d1), [
            'movimentacao_venda_origem_id' => $venda->id,
            'tipo_devolucao' => TipoDevolucao::COM_RETORNO_ESTOQUE->value,
            'id_unidade_negocio_retorno' => $c['unidade']->id,
            'qtd_fruta_um' => '2',
            'numero_nf_devolucao' => 'DEV-003',
        ])->assertOk();

        $this->assertSame('2.00', number_format($venda->fresh()->saldoDevolvivelUm(), 2, '.', ''));
        $this->assertSame('20.00', number_format($venda->fresh()->saldoDevolvivelKg(), 2, '.', ''));
    }

    public function test_devolucao_sem_retorno_dispensa_unidade_retorno_e_com_retorno_exige(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');

        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), [
                'movimentacao_venda_origem_id' => $venda->id,
                'tipo_devolucao' => TipoDevolucao::COM_RETORNO_ESTOQUE->value,
                'qtd_fruta_um' => '1',
                'numero_nf_devolucao' => 'DEV-SEM-UNIDADE',
            ])
            ->assertJsonValidationErrors(['id_unidade_negocio_retorno']);

        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), [
                'movimentacao_venda_origem_id' => $venda->id,
                'tipo_devolucao' => TipoDevolucao::SEM_RETORNO_ESTOQUE->value,
                'qtd_fruta_um' => '1',
                'numero_nf_devolucao' => 'DEV-SEM-RETORNO',
            ])
            ->assertCreated();
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
    private function cenarioBase(bool $origemHub = false, float $custoFaturamento = 0.0): array
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
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => $custoFaturamento,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->whereIn('id_unidade_negocio', [$unidade->id, $unidadeFaturamento->id])->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidadeFaturamento->id, 'custo_operacional' => $custoFaturamento, 'status_position' => true]);

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
    private function registrarVenda(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-VENDA',
            'id_empresa_origem' => $cenario['empresa_unidade']->id,
            'id_empresa_destino' => $cenario['empresa_cliente']->id,
            'id_unidade_negocio_faturamento' => $cenario['unidade_faturamento']->id,
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->orderByDesc('id')->firstOrFail();
    }

    private function registrarDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm, ?UnidadeNegocio $unidadeRetorno = null): Movimentacao
    {
        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), $this->payloadDevolucao($venda, $tipo, $qtdUm, $unidadeRetorno))
            ->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)->orderByDesc('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm, ?UnidadeNegocio $unidadeRetorno = null): array
    {
        $payload = [
            'movimentacao_venda_origem_id' => $venda->id,
            'tipo_devolucao' => $tipo->value,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_devolucao' => 'DEV-001',
            'motivo_devolucao' => 'Devolução de teste.',
        ];

        if ($tipo === TipoDevolucao::COM_RETORNO_ESTOQUE) {
            $payload['id_unidade_negocio_retorno'] = ($unidadeRetorno ?? $venda->empresaOrigem->entidade)->id;
        }

        return $payload;
    }

    private function cancelarDevolucaoAdmin(Movimentacao $devolucao): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.devolucoes.cancelar-admin', $devolucao), ['motivo' => 'Cancelamento administrativo de devolução.'])
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
