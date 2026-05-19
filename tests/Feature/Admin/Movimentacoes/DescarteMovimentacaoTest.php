<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasDescarteOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Models\CategoriaDescarte;
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
use Database\Seeders\CategoriaDescarteSeeder;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class DescarteMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_usuario_com_permissao_cria_descarte_e_reduz_estoque_preservando_preco_medio(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $descarte = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA, 'Avaria operacional.');

        $this->assertSame(CategoriaMovimentacaoTipo::Descarte->value, (int) $descarte->categoria_movimentacao_id);
        $this->assertSame(StatusMovimentacao::ID_SAIDA, (int) $descarte->status_movimentacao_id);
        $this->assertSame(CategoriaDescarte::ID_AVARIA, (int) $descarte->categoria_descarte_id);
        $this->assertSame('Avaria operacional.', $descarte->motivo_descarte);
        $this->assertSame('100.00', (string) $descarte->valor_total_movimentacao);
        $this->assertSame('5.00', (string) $descarte->preco_medio_fruta_kg);
        $this->assertSame('50.00', (string) $descarte->preco_medio_fruta_um);
        $this->assertCamposNulosDescarte($descarte);
        $this->assertCamposZeradosDescarte($descarte);
        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertSame(1, MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $c['unidade']->id)
            ->where('id_fruta', $c['fruta']->id)
            ->where('status_ultima_posicao', true)
            ->count());
        $this->assertNotNull($descarte->id_movimentacao_estoque_new);
        $this->assertDatabaseHas('movimentacao_historicos', [
            'movimentacao_cadeia_raiz_id' => $descarte->id,
            'origem' => MovimentacaoHistorico::ORIGEM_DESCARTE,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DESCARTE,
        ]);
    }

    public function test_criacao_multi_item_cria_um_descarte_por_fruta(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $fruta2 = Fruta::factory()->create([
            'kg_por_unidade_medicao' => 5,
        ]);
        $c2 = array_merge($c, ['fruta' => $fruta2]);
        $this->registrarCompra($c2, '4', '200,00');

        $this->actingAs($this->movimentacoesDescartesUsuario())
            ->postJson(route('admin.movimentacoes.descartes.store'), [
                'id_empresa_origem' => $c['empresa_unidade']->id,
                'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
                'itens' => [
                    ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2'],
                    ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '1'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->count());
    }

    public function test_formulario_criacao_nao_exibe_data_movimentacao(): void
    {
        $this->seedBase();
        $this->cenarioBase();

        $this->actingAs($this->movimentacoesDescartesUsuario())
            ->get(route('admin.movimentacoes.descartes.create'))
            ->assertOk()
            ->assertDontSee('name="data_movimentacao"', false)
            ->assertDontSee('Data da movimentação', false);
    }

    public function test_criacao_usa_hora_atual_como_data_movimentacao(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-17 18:40:00'));

        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $descarte = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);

        $this->assertSame('2026-05-17 18:40:00', $descarte->data_movimentacao?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_criacao_rejeita_data_movimentacao_enviada_pelo_usuario(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->movimentacoesDescartesUsuario())->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '1',
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
            'data_movimentacao' => '2020-01-01 10:00:00',
        ])->assertJsonValidationErrors('data_movimentacao');
    }

    public function test_usuario_sem_permissao_nao_cria_descarte(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '1',
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
        ])->assertForbidden();
    }

    public function test_validacoes_de_request_do_descarte(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $user = $this->movimentacoesDescartesUsuario();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '1',
        ])->assertJsonValidationErrors(['categoria_descarte_id']);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '1',
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
        ])->assertJsonValidationErrors(['id_empresa_destino']);

        $frutaInvalida = Fruta::factory()->create(['kg_por_unidade_medicao' => 0]);
        $this->actingAs($user)->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_fruta' => $frutaInvalida->id,
            'qtd_fruta_um' => '1',
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
        ])->assertJsonValidationErrors(['id_fruta']);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '1',
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
            'valor_total_movimentacao' => '999',
            'valor_icms_total' => '1',
            'id_frete' => 1,
        ])->assertJsonValidationErrors(['valor_total_movimentacao', 'valor_icms_total', 'id_frete']);
    }

    public function test_nao_permite_quantidade_maior_que_saldo(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '1', '50,00');

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);
    }

    public function test_update_cria_nova_versao_e_substituida_nao_entra_no_calculo(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $v1 = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);

        $this->actingAs($this->movimentacoesDescartesUsuario())->putJson(route('admin.movimentacoes.descartes.update', $v1), [
            'qtd_fruta_um' => '3',
            'categoria_descarte_id' => CategoriaDescarte::ID_QUEBRA,
            'motivo_descarte' => 'Quebra operacional.',
            'motivo_substituicao' => 'Correção da quantidade.',
        ])->assertOk();

        $v1->refresh();
        $v2 = Movimentacao::query()->findOrFail((int) $v1->substituida_por_id);

        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v2->status_registro);
        $this->assertSame(2, (int) $v2->versao);
        $this->assertSame('150.00', (string) $v2->valor_total_movimentacao);
        $this->assertSame(CategoriaDescarte::ID_QUEBRA, (int) $v2->categoria_descarte_id);
        $this->assertEstoque($c['unidade'], $c['fruta'], '70.00', '7.00', '5.00', '50.00', '350.00');
        $this->assertSame(1, Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('id_fruta', $c['fruta']->id)
            ->count());
    }

    public function test_cancelamento_administrativo_devolve_estoque_e_recalcula_futuros(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $d1 = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);
        $d2 = $this->registrarDescarte($c, '1', CategoriaDescarte::ID_QUEBRA);

        $this->cancelarDescarteAdmin($d1);

        $d1->refresh();
        $d2->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $d1->status_registro);
        $this->assertNotNull($d1->cancelada_em);
        $this->assertSame('50.00', (string) $d2->valor_total_movimentacao);
        $this->assertGreaterThan(1, (int) $d2->versao_replay);
        $this->assertEstoque($c['unidade'], $c['fruta'], '90.00', '9.00', '5.00', '50.00', '450.00');
        $this->assertDatabaseHas('movimentacao_historicos', [
            'movimentacao_cadeia_raiz_id' => $d1->id,
            'origem' => MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN,
            'acao' => MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN,
        ]);
    }

    public function test_cancelamento_administrativo_de_descarte_unico_retorna_saldo_ao_estoque(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $descarte = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);

        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');

        $this->cancelarDescarteAdmin($descarte);

        $descarte->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $descarte->status_registro);
        $this->assertEstoque($c['unidade'], $c['fruta'], '100.00', '10.00', '5.00', '50.00', '500.00');
    }

    public function test_cancelamento_reprocessa_descartes_restantes_quando_estoque_veio_de_saldo_inicial(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $c['unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);
        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $c['unidade']->id,
            'id_fruta' => $c['fruta']->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        $d1 = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);
        $d2 = $this->registrarDescarte($c, '1', CategoriaDescarte::ID_QUEBRA);

        $this->assertEstoque($c['unidade'], $c['fruta'], '70.00', '7.00', '5.00', '50.00', '350.00');

        $this->cancelarDescarteAdmin($d1);

        $d1->refresh();
        $d2->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $d1->status_registro);
        $this->assertGreaterThan(1, (int) $d2->versao_replay);
        $this->assertEstoque($c['unidade'], $c['fruta'], '90.00', '9.00', '5.00', '50.00', '450.00');
    }

    public function test_usuario_sem_permissao_nao_cancela_e_motivo_e_obrigatorio(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $descarte = $this->registrarDescarte($c, '1', CategoriaDescarte::ID_AVARIA);

        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.descartes.cancelar-admin', $descarte), [
            'motivo' => 'Sem permissão.',
        ])->assertForbidden();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.descartes.cancelar-admin', $descarte), [])
            ->assertJsonValidationErrors(['motivo']);
    }

    public function test_cancelamento_faz_rollback_transacional_se_replay_falhar(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $descarte = $this->registrarDescarte($c, '2', CategoriaDescarte::ID_AVARIA);

        $estoqueAntes = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail()->replicate();
        $ultimaAntes = MovimentacaoEstoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->where('status_ultima_posicao', true)->firstOrFail();
        $historicosAntes = MovimentacaoHistorico::query()->count();

        $mock = $this->createMock(ReprocessaSaidasDescarteOrigem::class);
        $mock->method('reprocessarSaidasDescarteNaUnidadeOrigem')->willThrowException(new RuntimeException('Falha simulada no replay.'));
        $this->app->instance(ReprocessaSaidasDescarteOrigem::class, $mock);

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.descartes.cancelar-admin', $descarte), [
                'motivo' => 'Teste rollback.',
            ])->assertServerError();

        $descarte->refresh();
        $estoqueDepois = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail();

        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $descarte->status_registro);
        $this->assertNull($descarte->cancelada_em);
        $this->assertNull($descarte->cancelada_por);
        $this->assertNull($descarte->motivo_cancelamento);
        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame((string) $estoqueAntes->valor_total_acumulado, (string) $estoqueDepois->valor_total_acumulado);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'id' => $ultimaAntes->id,
            'status_ultima_posicao' => true,
        ]);
        $this->assertSame($historicosAntes, MovimentacaoHistorico::query()->count());
    }

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            CategoriaDescarteSeeder::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cenarioBase(): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
        ]);

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->update(['status_position' => false]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => 0,
            'status_position' => true,
        ]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'fruta' => Fruta::factory()->comIcmsCeara([
                'entrada_nacional' => 0,
                'entrada_externo' => 0,
                'entrada_um' => FrutaUmIcms::KG->value,
            ])->create([
                'kg_por_unidade_medicao' => 10,
            ]),
            'frete' => Frete::factory()->create([
                'valor' => '0.00',
                'status_situacao' => FreteStatusSituacao::ABERTA->value,
            ]),
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

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_frete', $cenario['frete']->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarDescarte(array $cenario, string $qtdUm, int $categoriaId, ?string $motivo = null): Movimentacao
    {
        $this->actingAs($this->movimentacoesDescartesUsuario())->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $cenario['empresa_unidade']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'categoria_descarte_id' => $categoriaId,
            'motivo_descarte' => $motivo,
            'observacao' => 'Observação de teste.',
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function cancelarDescarteAdmin(Movimentacao $descarte): void
    {
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.descartes.cancelar-admin', $descarte), [
            'motivo' => 'Cancelamento administrativo de descarte.',
        ])->assertOk();
    }

    private function assertEstoque(UnidadeNegocio $unidade, Fruta $fruta, string $kg, string $um, string $precoKg, string $precoUm, string $valor): void
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame($kg, (string) $estoque->qtd_fruta_kg);
        $this->assertSame($um, (string) $estoque->qtd_fruta_um);
        $this->assertSame($precoKg, (string) $estoque->preco_medio_kg);
        $this->assertSame($precoUm, (string) $estoque->preco_medio_um);
        $this->assertSame($valor, (string) $estoque->valor_total_acumulado);
    }

    private function assertCamposNulosDescarte(Movimentacao $descarte): void
    {
        foreach ([
            'id_empresa_destino',
            'transferencia_origem_id',
            'status_transferencia',
            'qtd_recebida_um',
            'qtd_recebida_kg',
            'numero_nf_origem',
            'numero_nf_destino',
            'id_frete',
            'id_custo_operacional',
            'motivo_doacao',
            'observacao_recebimento',
        ] as $campo) {
            $this->assertNull($descarte->{$campo}, "Campo {$campo} deveria ficar null.");
        }
    }

    private function assertCamposZeradosDescarte(Movimentacao $descarte): void
    {
        foreach ([
            'valor_nf_total',
            'valor_nf_um',
            'valor_nf_kg',
            'valor_frete_kg',
            'valor_frete_um',
            'valor_frete_rateio',
            'valor_custo_operacional',
            'valor_icms_total',
            'valor_icms_kg',
            'valor_icms_um',
            'icms_convertido_kg',
        ] as $campo) {
            $this->assertSame('0.00', (string) $descarte->{$campo}, "Campo {$campo} deveria ficar zerado.");
        }
    }
}
