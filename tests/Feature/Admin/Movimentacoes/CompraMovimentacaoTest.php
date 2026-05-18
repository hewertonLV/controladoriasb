<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\UnidadeNegocio;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class CompraMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private function seedCategoriasEEstados(): void
    {
        $this->seed([
            EstadoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
        ]);
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: UnidadeNegocio, 3: Fruta, 4: Frete}
     */
    private function criarCenarioCompra(): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();

        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $empresaUnidade = $unidade->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->update(['status_position' => false]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.00',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '10.00',
            'icms_na_compra' => '2.00',
            'icms_ex_compra' => '1.00',
            'um_icms' => FrutaUmIcms::KG->value,
        ]);

        $frete = Frete::factory()->create(['valor' => '200.00']);

        return [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete];
    }

    public function test_registrar_compra_persiste_movimentacao_estoque_e_consolidado(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);

        $response = $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => 'R$ 1.000,00',
            'numero_nf_origem' => '123456',
            'id_frete' => $frete->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('movimentacoes', 1);

        $mov = Movimentacao::query()->firstOrFail();
        $this->assertSame(CategoriaMovimentacaoTipo::Compra->value, (int) $mov->categoria_movimentacao_id);
        $this->assertSame(1, $mov->numero_compra);
        $this->assertSame('123456', $mov->numero_nf_origem);
        $this->assertSame('50.00', (string) $mov->qtd_fruta_kg);
        $this->assertSame(1, $mov->versao);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $mov->status_registro);
        $this->assertNull($mov->movimentacao_origem_id);
        $this->assertNotNull($mov->data_movimentacao);
        $this->assertNotNull($mov->id_movimentacao_estoque_new);

        $novaMe = MovimentacaoEstoque::query()->findOrFail((int) $mov->id_movimentacao_estoque_new);
        $this->assertTrue($novaMe->status_ultima_posicao);
        $this->assertSame($mov->id, $novaMe->movimentacao_id);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('50.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('5.00', (string) $estoque->qtd_fruta_um);

        $this->assertSame(1, MovimentacaoEstoque::query()->where('status_ultima_posicao', true)->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->count());
    }

    public function test_numero_nf_compra_aceita_somente_numeros(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]))
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '5',
                'valor_nf_total' => '1000.00',
                'numero_nf_origem' => 'NF-123,45',
                'id_frete' => $frete->id,
            ])
            ->assertJsonValidationErrors(['numero_nf_origem']);
    }

    public function test_registrar_compra_multi_item_cria_uma_movimentacao_por_fruta(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete] = $this->criarCenarioCompra();
        $fruta2 = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '5.00',
            'icms_na_compra' => '0.00',
            'icms_ex_compra' => '0.00',
            'um_icms' => FrutaUmIcms::KG->value,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]))
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_frete' => $frete->id,
                'itens' => [
                    ['id_fruta' => $fruta->id, 'qtd_fruta_um' => '5', 'valor_nf_total' => '1000.00'],
                    ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300.00'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->count());
        $this->assertDatabaseHas('estoques', [
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5.00',
        ]);
        $this->assertDatabaseHas('estoques', [
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta2->id,
            'qtd_fruta_um' => '2.00',
        ]);
    }

    public function test_atualizar_compra_cria_nova_versao_imutavel(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $v1 = Movimentacao::query()->orderBy('id')->firstOrFail();
        $dataOperacional = $v1->data_movimentacao;
        $valorAntigo = (string) $v1->valor_nf_total;

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $v1), [
            'valor_nf_total' => '1.500,00',
            'motivo_substituicao' => 'Correção da NF',
        ])->assertOk();

        $this->assertDatabaseCount('movimentacoes', 2);

        $v1->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertNotNull($v1->substituida_por_id);
        $this->assertSame($valorAntigo, (string) $v1->valor_nf_total);

        $v2 = Movimentacao::query()->findOrFail((int) $v1->substituida_por_id);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v2->status_registro);
        $this->assertSame((int) $v1->numero_compra, (int) $v2->numero_compra);
        $this->assertSame(2, $v2->versao);
        $this->assertSame((int) $v1->id, (int) $v2->movimentacao_origem_id);
        $this->assertTrue($v2->data_movimentacao->equalTo($dataOperacional));
        $this->assertSame('1500.00', (string) $v2->valor_nf_total);
        $this->assertNotSame((string) $v1->valor_nf_total, (string) $v2->valor_nf_total);

        $this->assertSame(1, Movimentacao::query()->vigentesParaCalculo()->where('id_frete', $frete->id)->count());
        $this->assertSame(
            (float) $v2->qtd_fruta_kg,
            (float) Movimentacao::query()->vigentesParaCalculo()->where('id_frete', $frete->id)->sum('qtd_fruta_kg'),
        );

        $me = MovimentacaoEstoque::query()->where('status_ultima_posicao', true)->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertSame($v2->id, $me->movimentacao_id);

        $this->assertDatabaseCount('movimentacao_historicos', 1);
        $this->assertSame(MovimentacaoHistorico::ACAO_SUBSTITUICAO_VERSAO, MovimentacaoHistorico::query()->value('acao'));
    }

    public function test_atualizar_compra_antiga_recalcula_lancamentos_futuros_e_estoque(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $primeira = Movimentacao::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $segunda = Movimentacao::query()->whereKeyNot($primeira->id)->firstOrFail();
        $this->assertSame(1, $primeira->numero_compra);
        $this->assertSame(2, $segunda->numero_compra);

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $primeira), [
            'valor_nf_total' => '1.500,00',
            'motivo_substituicao' => 'Correção retroativa da NF',
        ])->assertOk();

        $primeira->refresh();
        $novaPrimeira = Movimentacao::query()->findOrFail((int) $primeira->substituida_por_id);
        $segunda->refresh();

        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $primeira->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $novaPrimeira->status_registro);
        $this->assertSame((int) $primeira->numero_compra, (int) $novaPrimeira->numero_compra);
        $this->assertSame('1500.00', (string) $novaPrimeira->valor_nf_total);
        $this->assertSame('36.00', (string) $novaPrimeira->preco_medio_fruta_kg);
        $this->assertSame('26.00', (string) $segunda->preco_medio_fruta_kg);
        $this->assertSame('100.00', (string) $segunda->saldo_estoque_fruta_kg);

        $meNovaPrimeira = MovimentacaoEstoque::query()->where('movimentacao_id', $novaPrimeira->id)->firstOrFail();
        $meSegunda = MovimentacaoEstoque::query()->where('movimentacao_id', $segunda->id)->firstOrFail();
        $this->assertSame((int) $meNovaPrimeira->id, (int) $segunda->id_movimentacao_estoque_old);
        $this->assertSame((int) $meSegunda->id, (int) $segunda->id_movimentacao_estoque_new);
        $this->assertTrue($meSegunda->status_ultima_posicao);
        $this->assertFalse($meNovaPrimeira->status_ultima_posicao);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('100.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('31.00', (string) $estoque->preco_medio_kg);
        $this->assertSame('3100.00', (string) $estoque->valor_total_acumulado);
        $this->assertSame(1, MovimentacaoEstoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->where('status_ultima_posicao', true)->count());
    }

    public function test_atualizar_compra_antiga_preserva_icms_e_custo_operacional_snapshotados(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $compra = Movimentacao::query()->firstOrFail();
        $this->assertSame('1.00', (string) $compra->valor_custo_operacional);
        $this->assertSame('3.00', (string) $compra->icms_convertido_kg);

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '9.00',
            'status_position' => true,
        ]);
        $fruta->forceFill([
            'icms_na_compra' => '90.00',
            'icms_ex_compra' => '9.00',
        ])->save();

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $compra), [
            'valor_nf_total' => '1.500,00',
            'motivo_substituicao' => 'Correção com cadastros atuais diferentes',
        ])->assertOk();

        $compra->refresh();
        $nova = Movimentacao::query()->findOrFail((int) $compra->substituida_por_id);

        $this->assertSame('1.00', (string) $nova->valor_custo_operacional);
        $this->assertSame('3.00', (string) $nova->icms_convertido_kg);
        $this->assertSame('38.00', (string) $nova->preco_medio_fruta_kg);
    }

    public function test_consulta_e_update_por_id_substituido_resolvem_versao_ativa(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
            Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $v1 = Movimentacao::query()->firstOrFail();

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $v1), [
            'valor_nf_total' => '1.500,00',
            'motivo_substituicao' => 'Primeira correção',
        ])->assertOk();

        $v1->refresh();
        $v2 = Movimentacao::query()->findOrFail((int) $v1->substituida_por_id);

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.compras.show', $v1->id))
            ->assertOk()
            ->assertSee('Compra #'.$v2->numero_compra, false)
            ->assertSee('Linha do tempo da compra #'.$v2->numero_compra, false)
            ->assertSee('Data da atualização desta versão', false);

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $v1->id), [
            'valor_nf_total' => '1.800,00',
            'motivo_substituicao' => 'Segunda correção enviada pelo registro original',
        ])->assertOk();

        $v1->refresh();
        $v2->refresh();
        $v3 = Movimentacao::query()->findOrFail((int) $v2->substituida_por_id);

        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v2->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v3->status_registro);
        $this->assertSame((int) $v2->id, (int) $v1->substituida_por_id);
        $this->assertSame((int) $v3->id, (int) $v2->substituida_por_id);
        $this->assertSame((int) $v1->id, (int) $v3->movimentacao_origem_id);
        $this->assertSame((int) $v1->numero_compra, (int) $v2->numero_compra);
        $this->assertSame((int) $v1->numero_compra, (int) $v3->numero_compra);
        $this->assertSame('1800.00', (string) $v3->valor_nf_total);
    }

    public function test_store_requer_permissao_criar(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '5',
                'valor_nf_total' => '1000.00',
                'id_frete' => $frete->id,
            ])
            ->assertForbidden();
    }

    public function test_store_rejeita_campos_calculados_enviados_manualmente(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
            'qtd_fruta_kg' => '999.99',
        ])->assertUnprocessable();
    }

    public function test_update_rejeita_campos_que_nao_devem_ser_alterados_via_request(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $v1 = Movimentacao::query()->firstOrFail();

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $v1), [
            'valor_nf_total' => '1500.00',
            'qtd_fruta_um' => '99',
        ])->assertUnprocessable();
    }

    public function test_index_show_create_respeitam_permissoes(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $criada = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]))
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '5',
                'valor_nf_total' => '1000.00',
                'id_frete' => $frete->id,
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.movimentacoes.compras.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.movimentacoes.compras.show', $criada))
            ->assertForbidden();

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.movimentacoes.compras.create'))
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR]))
            ->get(route('admin.movimentacoes.compras.index'))
            ->assertOk();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR]))
            ->get(route('admin.movimentacoes.compras.show', $criada))
            ->assertOk();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]))
            ->get(route('admin.movimentacoes.compras.create'))
            ->assertOk();
    }

    public function test_create_carrega_selects_filtrados(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $unidadeSemEstoque = UnidadeNegocio::factory()->create([
            'possui_estoque' => false,
            'id_estado' => Estado::ID_CEARA,
            'nome' => 'Unidade SB Teste Sem Estoque '.uniqid(),
        ]);
        $cliente = Cliente::factory()->create([
            'razao_social' => 'Cliente SB Sem Possui Estoque '.uniqid(),
        ]);
        $fornecedorExtra = Fornecedor::factory()->create([
            'razao_social' => 'Fornecedor SB Sem Possui Estoque '.uniqid(),
        ]);

        $nomeFreteEncerrado = 'Frete Encerrado SB Teste '.uniqid();
        $freteFechado = Frete::factory()->create([
            'nome' => $nomeFreteEncerrado,
            'status_situacao' => FreteStatusSituacao::ENCERRADA->value,
        ]);

        $nomeFrutaSemKg = 'Fruta SB Sem KG '.uniqid();
        Fruta::factory()->create([
            'nome' => $nomeFrutaSemKg,
            'kg_por_unidade_medicao' => '0.00',
        ]);

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);

        $html = $this->actingAs($user)->get(route('admin.movimentacoes.compras.create'))->assertOk()->getContent();
        $destinoSelect = $this->selectHtml((string) $html, 'id_empresa_destino');

        $this->assertStringContainsString((string) $empresaFornecedor->id, (string) $html);
        $this->assertStringContainsString((string) $empresaUnidade->id, (string) $html);
        $this->assertStringContainsString((string) $empresaUnidade->id, $destinoSelect);
        $this->assertStringNotContainsString($unidadeSemEstoque->nome, $destinoSelect);
        $this->assertStringNotContainsString($cliente->razao_social, $destinoSelect);
        $this->assertStringNotContainsString($fornecedorExtra->razao_social, $destinoSelect);
        $this->assertStringContainsString((string) $fruta->id, (string) $html);
        $this->assertStringContainsString((string) $frete->id, (string) $html);
        $this->assertStringNotContainsString($nomeFreteEncerrado, (string) $html);
        $this->assertStringNotContainsString($nomeFrutaSemKg, (string) $html);
    }

    public function test_menu_dashboard_exibe_compra_com_permissao_e_oculta_sem(): void
    {
        $comVisualizar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR]);
        $this->actingAs($comVisualizar)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Compra', false)
            ->assertSee('admin/movimentacoes/compras', false);

        $semPermissao = $this->userWithoutEmpresaPermissions();
        $this->actingAs($semPermissao)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('admin/movimentacoes/compras', false);
    }

    public function test_menu_dashboard_exibe_compra_para_usuario_apenas_com_criar(): void
    {
        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Compra', false)
            ->assertSee('/admin/movimentacoes/compras/criar', false);
    }

    public function test_store_web_redireciona_para_show(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);

        $this->actingAs($user)->post(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaFornecedor->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000,00',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $mov = Movimentacao::query()->firstOrFail();
        $this->assertSame(CategoriaMovimentacaoTipo::Compra->value, (int) $mov->categoria_movimentacao_id);
    }

    public function test_cliente_nao_aparece_como_fornecedor_no_formulario(): void
    {
        $this->seedCategoriasEEstados();

        $cliente = Cliente::factory()->create([
            'razao_social' => 'Cliente SB Não Fornecedor '.uniqid(),
        ]);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();

        [$empresaFornecedor, $empresaUnidade, , $fruta, $frete] = $this->criarCenarioCompra();

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $empresaCliente->id,
            'id_empresa_destino' => $empresaUnidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertUnprocessable();

        $html = $this->actingAs($user)->get(route('admin.movimentacoes.compras.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString($cliente->razao_social, (string) $html);
        $this->assertStringContainsString((string) $empresaFornecedor->id, (string) $html);
    }

    private function selectHtml(string $html, string $id): string
    {
        $pattern = sprintf('/<select[^>]*id="%s"[^>]*>.*?<\/select>/s', preg_quote($id, '/'));

        return preg_match($pattern, $html, $matches) === 1 ? $matches[0] : '';
    }
}
