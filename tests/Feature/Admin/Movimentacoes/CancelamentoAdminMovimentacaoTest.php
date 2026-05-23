<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEstoqueDestinoCompra;
use App\Contracts\Movimentacoes\ReprocessaSaidasTransferenciaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Enums\StatusRecebimentoTransferencia;
use App\Enums\StatusTransferenciaOperacional;
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
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Movimentacoes\CancelarCompraMovimentacaoService;
use App\Services\Movimentacoes\CancelarTransferenciaMovimentacaoAdminService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class CancelamentoAdminMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
        ]);
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: UnidadeNegocio, 3: Fruta, 4: Frete}
     */
    private function cenarioCompra(): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();

        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $empresaUnidade = $unidade->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.00',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '2.00',
            'entrada_externo' => '1.00',
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $frete = Frete::factory()->create(['valor' => '200.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]);

        return [$empresaFornecedor, $empresaUnidade, $unidade, $fruta, $frete];
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: UnidadeNegocio, 3: UnidadeNegocio, 4: Fruta}
     */
    private function cenarioTransferencia(): array
    {
        $unidadeOrigem = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
        ]);
        $empresaOrigem = $unidadeOrigem->registroCorporativo()->firstOrFail();

        $unidadeDestino = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $empresaDestino = $unidadeDestino->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidadeDestino->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '2.00',
            'entrada_externo' => '1.00',
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $estoqueOrigem = Estoque::factory()->create([
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueOrigem->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $unidadeDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_acumulado' => '0.00',
        ]);

        return [$empresaOrigem, $empresaDestino, $unidadeOrigem, $unidadeDestino, $fruta];
    }

    public function test_admin_cancela_compra_com_permissao_cancelar_admin(): void
    {
        $this->seedBase();
        [$ef, $eu, $unidade, $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();

        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $mov), [
            'motivo' => 'Erro operacional na conferência da NF.',
        ])->assertOk();

        $mov->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $mov->status_registro);
        $this->assertNotNull($mov->cancelada_em);
        $this->assertSame($admin->id, (int) $mov->cancelada_por);
        $this->assertNotNull($mov->motivo_cancelamento);

        $estoque = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertSame('0.00', (string) $estoque->qtd_fruta_kg);
    }

    public function test_usuario_sem_permissao_nem_role_admin_nao_cancela_compra(): void
    {
        $this->seedBase();
        [$ef, $eu, , $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();

        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.cancelar-admin', $mov), [
            'motivo' => 'Tentativa indevida.',
        ])->assertForbidden();
    }

    public function test_role_administrador_cancela_compra_sem_permissao_especifica(): void
    {
        $this->seedBase();
        [$ef, $eu, $unidade, $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();

        $this->resetPermissionCache();
        $admin = User::factory()->create(['must_change_password' => false, 'ativo' => true]);
        $admin->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $mov), [
            'motivo' => 'Decisão da diretoria.',
        ])->assertOk();

        $mov->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $mov->status_registro);
    }

    public function test_cancelamento_compra_recalcula_lancamento_futuro_e_frete(): void
    {
        $this->seedBase();
        [$ef, $eu, $unidade, $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $movs = Movimentacao::query()->orderBy('id')->get();
        $this->assertCount(2, $movs);
        $primeira = $movs->first();
        $segundaAntes = $movs->last();
        $valorFreteKgAntes = (float) $segundaAntes->valor_frete_kg;

        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);
        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $primeira), [
            'motivo' => 'NF cancelada junto ao fornecedor.',
        ])->assertOk();

        $segunda = Movimentacao::query()->whereKey($segundaAntes->id)->firstOrFail();
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $segunda->status_registro);
        $this->assertGreaterThan($valorFreteKgAntes, (float) $segunda->valor_frete_kg);

        $frete->refresh();
        $this->assertGreaterThan(0, (float) $frete->valor_fruta_kg);
    }

    public function test_motivo_obrigatorio_no_cancelamento_admin_compra(): void
    {
        $this->seedBase();
        [$ef, $eu, , $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $mov), [])
            ->assertUnprocessable();
    }

    public function test_auditoria_cancelamento_admin_compra_registrada(): void
    {
        $this->seedBase();
        [$ef, $eu, , $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $mov), [
            'motivo' => 'Auditoria interna.',
        ])->assertOk();

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN,
            'origem' => MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cancela_transferencia_recebida_e_reverte_destino(): void
    {
        $this->seedBase();
        [$eo, $ed, $uo, $ud, $fruta] = $this->cenarioTransferencia();

        $user = $this->movimentacoesTransferenciasUsuario([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $eo->id,
            'id_empresa_destino' => $ed->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
        ])->assertCreated();

        $saida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $anchor = (int) $saida->transferencia_origem_id;

        $entrada = Movimentacao::query()
            ->whereKey((int) $saida->pareada_movimentacao_id)
            ->firstOrFail();

        $estoqueDestAntes = Estoque::query()->where('id_unidade_negocio', $ud->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $kgAntes = (float) $estoqueDestAntes->qtd_fruta_kg;

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.cancelar-admin', $anchor), [
            'motivo' => 'Operação invalidada pela controladoria.',
        ])->assertOk();

        $saida->refresh();
        $entrada->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $saida->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $entrada->status_registro);
        $this->assertSame(StatusTransferenciaOperacional::CANCELADA->value, $saida->status_transferencia);

        $estoqueDestDepois = Estoque::query()->where('id_unidade_negocio', $ud->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertLessThan($kgAntes, (float) $estoqueDestDepois->qtd_fruta_kg + 0.0001);

        $this->assertSame(2, MovimentacaoHistorico::query()
            ->where('acao', MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN)
            ->where('origem', MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN)
            ->count());
    }

    public function test_usuario_sem_permissao_cancelar_admin_transferencia(): void
    {
        $this->seedBase();
        [$eo, $ed, , , $fruta] = $this->cenarioTransferencia();

        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $eo->id,
            'id_empresa_destino' => $ed->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
        ])->assertCreated();

        $saida = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();
        $anchor = (int) $saida->transferencia_origem_id;

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.cancelar-admin', $anchor), [
            'motivo' => 'Sem autorização.',
        ])->assertForbidden();
    }

    public function test_transferencia_cancelamento_admin_devolve_estoque_origem(): void
    {
        $this->seedBase();
        [$eo, $ed, $uo, , $fruta] = $this->cenarioTransferencia();

        $estoqueOrigAntes = Estoque::query()->where('id_unidade_negocio', $uo->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $kgAntes = (float) $estoqueOrigAntes->qtd_fruta_kg;

        $user = $this->movimentacoesTransferenciasUsuario([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $eo->id,
            'id_empresa_destino' => $ed->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
        ])->assertCreated();

        $saida = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();
        $anchor = (int) $saida->transferencia_origem_id;

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.cancelar-admin', $anchor), [
            'motivo' => 'Solicitação do remetente.',
        ])->assertOk();

        $estoqueOrigDepois = Estoque::query()->where('id_unidade_negocio', $uo->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertGreaterThan($kgAntes - 0.01, (float) $estoqueOrigDepois->qtd_fruta_kg);
    }

    public function test_replay_ignora_movimentacoes_canceladas_no_destino(): void
    {
        $this->seedBase();
        [$ef, $eu, $unidade, $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '3',
            'valor_nf_total' => '600.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $movs = Movimentacao::query()->orderBy('id')->get();
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);
        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $movs->first()), [
            'motivo' => 'NF estornada.',
        ])->assertOk();

        $this->assertSame(1, Movimentacao::query()->vigentesParaCalculo()->where('id_fruta', $fruta->id)->count());

        $estoque = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertSame('30.00', (string) $estoque->qtd_fruta_kg);
    }

    public function test_rollback_transacional_cancelamento_admin_compra_quando_replay_falha(): void
    {
        $this->seedBase();
        [$ef, $eu, $unidade, $fruta, $frete] = $this->cenarioCompra();

        $criar = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $this->actingAs($criar)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $ef->id,
            'id_empresa_destino' => $eu->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '5',
            'valor_nf_total' => '1000.00',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $mov = Movimentacao::query()->firstOrFail();
        $snapshot = $this->capturarSnapshotCancelamentoAdminCompra(
            (int) $mov->id,
            $unidade->id,
            (int) $fruta->id,
            (int) $frete->id,
        );

        $this->mock(ReprocessaEstoqueDestinoCompra::class, function ($mock): void {
            $mock->shouldReceive('reprocessarEstoqueDestinoUnidadeFruta')
                ->once()
                ->andThrow(new RuntimeException('falha simulada no replay de destino'));
        });

        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);
        $service = app(CancelarCompraMovimentacaoService::class);

        try {
            $service->executar($mov, $admin, 'Simulação de falha após marcar CANCELADA e recalcular frete.');
            $this->fail('Esperava RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada no replay de destino', $e->getMessage());
        }

        $this->assertSnapshotCancelamentoAdminCompraIgual($snapshot, (int) $mov->id, $unidade->id, (int) $fruta->id, (int) $frete->id);
    }

    public function test_rollback_transacional_cancelamento_admin_transferencia_quando_replay_origem_falha(): void
    {
        $this->seedBase();
        [$eo, $ed, $uo, $ud, $fruta] = $this->cenarioTransferencia();

        $frete = Frete::factory()->create([
            'valor' => '300.00',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
        ]);

        $user = $this->movimentacoesTransferenciasUsuario([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $eo->id,
            'id_empresa_destino' => $ed->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
            'id_frete' => $frete->id,
        ])->assertCreated();

        $saida = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();
        $entrada = Movimentacao::query()->whereKey((int) $saida->pareada_movimentacao_id)->firstOrFail();
        $anchor = (int) $saida->transferencia_origem_id;

        $snapshot = $this->capturarSnapshotCancelamentoAdminTransferencia(
            (int) $saida->id,
            (int) $entrada->id,
            $uo->id,
            $ud->id,
            (int) $fruta->id,
            (int) $frete->id,
        );

        $this->mock(ReprocessaSaidasTransferenciaOrigem::class, function ($mock): void {
            $mock->shouldReceive('reprocessarSaidasTransferenciaNaUnidadeOrigem')
                ->once()
                ->andThrow(new RuntimeException('falha simulada no replay de saídas na origem'));
        });

        $service = app(CancelarTransferenciaMovimentacaoAdminService::class);

        try {
            $service->executar($anchor, $user, 'Simulação de falha após estorno e marcar pernas como CANCELADAS.');
            $this->fail('Esperava RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada no replay de saídas na origem', $e->getMessage());
        }

        $this->assertSnapshotCancelamentoAdminTransferenciaIgual(
            $snapshot,
            (int) $saida->id,
            (int) $entrada->id,
            $uo->id,
            $ud->id,
            (int) $fruta->id,
            (int) $frete->id,
        );
    }

    /**
     * @return array{
     *     mov: array<string, mixed>,
     *     estoque: array<string, string>,
     *     me: list<array<string, mixed>>,
     *     frete: array<string, string>,
     *     historico_count: int,
     * }
     */
    private function capturarSnapshotCancelamentoAdminCompra(int $movimentoId, int $unidadeDestinoId, int $frutaId, int $freteId): array
    {
        $mov = Movimentacao::query()->findOrFail($movimentoId);
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestinoId)
            ->where('id_fruta', $frutaId)
            ->firstOrFail();

        return [
            'mov' => [
                'status_registro' => $mov->status_registro,
                'cancelada_por' => $mov->cancelada_por,
                'cancelada_em' => $mov->cancelada_em?->format('Y-m-d H:i:s.u'),
                'motivo_cancelamento' => $mov->motivo_cancelamento,
                'valor_frete_kg' => (string) $mov->valor_frete_kg,
                'valor_frete_rateio' => (string) $mov->valor_frete_rateio,
                'valor_frete_um' => (string) $mov->valor_frete_um,
                'preco_medio_fruta_kg' => (string) $mov->preco_medio_fruta_kg,
                'id_movimentacao_estoque_old' => $mov->id_movimentacao_estoque_old,
                'id_movimentacao_estoque_new' => $mov->id_movimentacao_estoque_new,
            ],
            'estoque' => [
                'qtd_fruta_kg' => (string) $estoque->qtd_fruta_kg,
                'qtd_fruta_um' => (string) $estoque->qtd_fruta_um,
                'preco_medio_kg' => (string) $estoque->preco_medio_kg,
                'preco_medio_um' => (string) $estoque->preco_medio_um,
                'valor_total_acumulado' => (string) $estoque->valor_total_acumulado,
            ],
            'me' => $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeDestinoId, $frutaId),
            'frete' => $this->serializarFrete($freteId),
            'historico_count' => MovimentacaoHistorico::query()->count(),
        ];
    }

    /**
     * @param  array{
     *     mov: array<string, mixed>,
     *     estoque: array<string, string>,
     *     me: list<array<string, mixed>>,
     *     frete: array<string, string>,
     *     historico_count: int,
     * }  $snapshot
     */
    private function assertSnapshotCancelamentoAdminCompraIgual(
        array $snapshot,
        int $movimentoId,
        int $unidadeDestinoId,
        int $frutaId,
        int $freteId,
    ): void {
        $mov = Movimentacao::query()->findOrFail($movimentoId);
        $this->assertSame($snapshot['mov']['status_registro'], $mov->status_registro);
        $this->assertSame($snapshot['mov']['cancelada_por'], $mov->cancelada_por);
        $this->assertSame($snapshot['mov']['cancelada_em'], $mov->cancelada_em?->format('Y-m-d H:i:s.u'));
        $this->assertSame($snapshot['mov']['motivo_cancelamento'], $mov->motivo_cancelamento);
        $this->assertSame($snapshot['mov']['valor_frete_kg'], (string) $mov->valor_frete_kg);
        $this->assertSame($snapshot['mov']['valor_frete_rateio'], (string) $mov->valor_frete_rateio);
        $this->assertSame($snapshot['mov']['valor_frete_um'], (string) $mov->valor_frete_um);
        $this->assertSame($snapshot['mov']['preco_medio_fruta_kg'], (string) $mov->preco_medio_fruta_kg);
        $this->assertSame($snapshot['mov']['id_movimentacao_estoque_old'], $mov->id_movimentacao_estoque_old);
        $this->assertSame($snapshot['mov']['id_movimentacao_estoque_new'], $mov->id_movimentacao_estoque_new);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestinoId)
            ->where('id_fruta', $frutaId)
            ->firstOrFail();
        $this->assertSame($snapshot['estoque']['qtd_fruta_kg'], (string) $estoque->qtd_fruta_kg);
        $this->assertSame($snapshot['estoque']['qtd_fruta_um'], (string) $estoque->qtd_fruta_um);
        $this->assertSame($snapshot['estoque']['preco_medio_kg'], (string) $estoque->preco_medio_kg);
        $this->assertSame($snapshot['estoque']['preco_medio_um'], (string) $estoque->preco_medio_um);
        $this->assertSame($snapshot['estoque']['valor_total_acumulado'], (string) $estoque->valor_total_acumulado);

        $this->assertSame(
            $snapshot['me'],
            $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeDestinoId, $frutaId),
        );
        $this->assertSame($snapshot['frete'], $this->serializarFrete($freteId));
        $this->assertSame($snapshot['historico_count'], MovimentacaoHistorico::query()->count());
    }

    /**
     * @return array{
     *     saida: array<string, mixed>,
     *     entrada: array<string, mixed>,
     *     estoque_origem: array<string, string>,
     *     estoque_destino: array<string, string>,
     *     me_origem: list<array<string, mixed>>,
     *     me_destino: list<array<string, mixed>>,
     *     frete: array<string, string>,
     *     historico_count: int,
     * }
     */
    private function capturarSnapshotCancelamentoAdminTransferencia(
        int $saidaId,
        int $entradaId,
        int $unidadeOrigemId,
        int $unidadeDestinoId,
        int $frutaId,
        int $freteId,
    ): array {
        $saida = Movimentacao::query()->findOrFail($saidaId);
        $entrada = Movimentacao::query()->findOrFail($entradaId);
        $eo = Estoque::query()->where('id_unidade_negocio', $unidadeOrigemId)->where('id_fruta', $frutaId)->firstOrFail();
        $ed = Estoque::query()->where('id_unidade_negocio', $unidadeDestinoId)->where('id_fruta', $frutaId)->firstOrFail();

        return [
            'saida' => $this->serializarMovimentacaoTransferenciaPerna($saida),
            'entrada' => $this->serializarMovimentacaoTransferenciaPerna($entrada),
            'estoque_origem' => $this->serializarEstoque($eo),
            'estoque_destino' => $this->serializarEstoque($ed),
            'me_origem' => $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeOrigemId, $frutaId),
            'me_destino' => $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeDestinoId, $frutaId),
            'frete' => $this->serializarFrete($freteId),
            'historico_count' => MovimentacaoHistorico::query()->count(),
        ];
    }

    /**
     * @param  array{
     *     saida: array<string, mixed>,
     *     entrada: array<string, mixed>,
     *     estoque_origem: array<string, string>,
     *     estoque_destino: array<string, string>,
     *     me_origem: list<array<string, mixed>>,
     *     me_destino: list<array<string, mixed>>,
     *     frete: array<string, string>,
     *     historico_count: int,
     * }  $snapshot
     */
    private function assertSnapshotCancelamentoAdminTransferenciaIgual(
        array $snapshot,
        int $saidaId,
        int $entradaId,
        int $unidadeOrigemId,
        int $unidadeDestinoId,
        int $frutaId,
        int $freteId,
    ): void {
        $saida = Movimentacao::query()->findOrFail($saidaId);
        $entrada = Movimentacao::query()->findOrFail($entradaId);
        $this->assertSame($snapshot['saida'], $this->serializarMovimentacaoTransferenciaPerna($saida));
        $this->assertSame($snapshot['entrada'], $this->serializarMovimentacaoTransferenciaPerna($entrada));

        $eo = Estoque::query()->where('id_unidade_negocio', $unidadeOrigemId)->where('id_fruta', $frutaId)->firstOrFail();
        $ed = Estoque::query()->where('id_unidade_negocio', $unidadeDestinoId)->where('id_fruta', $frutaId)->firstOrFail();
        $this->assertSame($snapshot['estoque_origem'], $this->serializarEstoque($eo));
        $this->assertSame($snapshot['estoque_destino'], $this->serializarEstoque($ed));

        $this->assertSame(
            $snapshot['me_origem'],
            $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeOrigemId, $frutaId),
        );
        $this->assertSame(
            $snapshot['me_destino'],
            $this->serializarMovimentacoesEstoqueUnidadeFruta($unidadeDestinoId, $frutaId),
        );
        $this->assertSame($snapshot['frete'], $this->serializarFrete($freteId));
        $this->assertSame($snapshot['historico_count'], MovimentacaoHistorico::query()->count());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializarMovimentacoesEstoqueUnidadeFruta(int $unidadeNegocioId, int $frutaId): array
    {
        return MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeNegocioId)
            ->where('id_fruta', $frutaId)
            ->orderBy('id')
            ->get()
            ->map(static function (MovimentacaoEstoque $me): array {
                return [
                    'id' => $me->id,
                    'movimentacao_id' => $me->movimentacao_id,
                    'status_ultima_posicao' => (bool) $me->status_ultima_posicao,
                    'qtd_fruta_kg' => (string) $me->qtd_fruta_kg,
                    'qtd_fruta_um' => (string) $me->qtd_fruta_um,
                    'preco_medio_kg' => (string) $me->preco_medio_kg,
                    'preco_medio_um' => (string) $me->preco_medio_um,
                    'valor_total_fruta' => (string) $me->valor_total_fruta,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function serializarFrete(int $freteId): array
    {
        $f = Frete::query()->findOrFail($freteId);

        return [
            'valor' => (string) $f->valor,
            'valor_fruta_kg' => (string) $f->valor_fruta_kg,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function serializarEstoque(Estoque $e): array
    {
        return [
            'qtd_fruta_kg' => (string) $e->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $e->qtd_fruta_um,
            'preco_medio_kg' => (string) $e->preco_medio_kg,
            'preco_medio_um' => (string) $e->preco_medio_um,
            'valor_total_acumulado' => (string) $e->valor_total_acumulado,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarMovimentacaoTransferenciaPerna(Movimentacao $m): array
    {
        return [
            'status_registro' => $m->status_registro,
            'status_transferencia' => $m->status_transferencia,
            'cancelada_por' => $m->cancelada_por,
            'cancelada_em' => $m->cancelada_em?->format('Y-m-d H:i:s.u'),
            'motivo_cancelamento' => $m->motivo_cancelamento,
            'valor_frete_kg' => (string) $m->valor_frete_kg,
            'valor_frete_rateio' => (string) $m->valor_frete_rateio,
            'id_movimentacao_estoque_new' => $m->id_movimentacao_estoque_new,
        ];
    }
}
