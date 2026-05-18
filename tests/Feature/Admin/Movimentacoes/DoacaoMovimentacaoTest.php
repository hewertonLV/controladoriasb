<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\CancelarDoacaoMovimentacaoAdminService;
use App\Services\Movimentacoes\ReplayLinhaTempoEstoqueService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class DoacaoMovimentacaoTest extends TestCase
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
     * @return array{0: Empresa, 1: UnidadeNegocio, 2: Fruta}
     */
    private function criarCenarioDoacao(): array
    {
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
        ]);
        $empresaOrigem = $unidade->registroCorporativo()->firstOrFail();

        $fruta = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '4.00',
            'preco_medio_um' => '40.00',
            'valor_total_acumulado' => '400.00',
        ]);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '4.00',
            'preco_medio_um' => '40.00',
            'valor_total_fruta' => '400.00',
            'status_ultima_posicao' => true,
        ]);

        return [$empresaOrigem, $unidade, $fruta];
    }

    public function test_usuario_com_permissao_cria_doacao_e_reduz_estoque_preservando_preco_medio(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'Campanha social',
        ])->assertRedirect();

        $mov = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $this->assertNull($mov->id_empresa_destino);
        $this->assertSame('1.00', (string) $mov->qtd_fruta_um);
        $this->assertSame('10.00', (string) $mov->qtd_fruta_kg);
        $this->assertSame('0.00', (string) $mov->valor_nf_total);
        $this->assertSame('0.00', (string) $mov->valor_nf_um);
        $this->assertSame('0.00', (string) $mov->valor_nf_kg);
        $this->assertSame('40.00', (string) $mov->valor_total_movimentacao);
        $this->assertSame(40.0, $mov->valorEconomicoParaRelatorio());
        $this->assertSame('4.00', (string) $mov->preco_medio_fruta_kg);
        $this->assertNull($mov->id_frete);
        $this->assertSame('0.00', (string) $mov->valor_frete_kg);
        $this->assertSame('0.00', (string) $mov->icms_convertido_kg);
        $this->assertNull($mov->id_custo_operacional);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame('90.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('9.00', (string) $estoque->qtd_fruta_um);
        $this->assertSame('4.00', (string) $estoque->preco_medio_kg);
        $this->assertSame('40.00', (string) $estoque->preco_medio_um);
        $this->assertSame('360.00', (string) $estoque->valor_total_acumulado);

        $this->assertSame(1, MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->count());

        $me = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->firstOrFail();
        $this->assertSame('4.00', (string) $me->preco_medio_kg);
        $this->assertSame('40.00', (string) $me->preco_medio_um);
        $this->assertSame('360.00', (string) $me->valor_total_fruta);

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_DOACAO,
            'origem' => MovimentacaoHistorico::ORIGEM_DOACAO,
        ]);
    }

    public function test_criacao_multi_item_cria_uma_doacao_por_fruta(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $fruta2 = Fruta::factory()->create(['kg_por_unidade_medicao' => '5.00']);
        $estoque2 = Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta2->id,
            'qtd_fruta_kg' => '50.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '6.00',
            'preco_medio_um' => '30.00',
            'valor_total_acumulado' => '300.00',
        ]);
        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque2->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta2->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '50.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '6.00',
            'preco_medio_um' => '30.00',
            'valor_total_fruta' => '300.00',
            'status_ultima_posicao' => true,
        ]);

        $this->actingAs($this->movimentacoesDoacoesUsuario())
            ->postJson(route('admin.movimentacoes.doacoes.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'motivo_doacao' => 'Campanha social',
                'itens' => [
                    ['id_fruta' => $fruta->id, 'qtd_fruta_um' => '1'],
                    ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '2'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->count());
    }

    public function test_formulario_criacao_nao_exibe_data_movimentacao(): void
    {
        $this->seedBase();
        $this->criarCenarioDoacao();

        $this->actingAs($this->movimentacoesDoacoesUsuario())
            ->get(route('admin.movimentacoes.doacoes.create'))
            ->assertOk()
            ->assertDontSee('name="data_movimentacao"', false)
            ->assertDontSee('Data da movimentação', false);
    }

    public function test_criacao_usa_hora_atual_como_data_movimentacao(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-17 18:30:00'));

        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'Campanha social',
        ])->assertRedirect();

        $mov = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $this->assertSame('2026-05-17 18:30:00', $mov->data_movimentacao?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_criacao_rejeita_data_movimentacao_enviada_pelo_usuario(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'Campanha social',
            'data_movimentacao' => '2020-01-01 10:00:00',
        ])->assertSessionHasErrors('data_movimentacao');

        $this->assertDatabaseMissing('movimentacoes', [
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Doacao->value,
            'id_fruta' => $fruta->id,
        ]);
    }

    public function test_usuario_sem_permissao_nao_cria_doacao(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->userWithoutEmpresaPermissions();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'X',
        ])->assertForbidden();
    }

    public function test_cliente_destino_quando_informado_precisa_ser_cliente(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'X',
        ])->assertSessionHasErrors('id_empresa_destino');
    }

    public function test_cliente_destino_opcional_valido(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $cliente = Cliente::factory()->create(['id_unidade_negocio' => $unidade->id]);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaCliente->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'Doação a cliente',
        ])->assertRedirect();

        $mov = Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)->firstOrFail();
        $this->assertSame((int) $empresaCliente->id, (int) $mov->id_empresa_destino);
    }

    public function test_nao_permite_quantidade_maior_que_saldo(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '11',
            'motivo_doacao' => 'X',
        ])->assertStatus(500);
    }

    public function test_nao_permite_fruta_com_kg_por_unidade_invalido(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $fruta->forceFill(['kg_por_unidade_medicao' => '0'])->save();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'X',
        ])->assertSessionHasErrors('id_fruta');
    }

    public function test_segunda_doacao_usa_mesmo_preco_medio_e_grava_valor_movimentacao(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D1',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D2',
        ])->assertRedirect();

        $d2 = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('4.00', (string) $d2->preco_medio_fruta_kg);
        $this->assertSame('40.00', (string) $d2->valor_total_movimentacao);
        $this->assertSame('0.00', (string) $d2->valor_nf_total);
    }

    public function test_campos_calculados_proibidos_no_request(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'X',
            'qtd_fruta_kg' => '999',
        ])->assertSessionHasErrors('qtd_fruta_kg');
    }

    public function test_valor_total_movimentacao_proibido_no_request(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'X',
            'valor_total_movimentacao' => '999.00',
        ])->assertSessionHasErrors('valor_total_movimentacao');
    }

    public function test_update_cria_nova_versao_e_substituicao_auditoria(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'V1',
        ])->assertRedirect();

        $v1 = Movimentacao::query()->where('versao', 1)->firstOrFail();

        $this->actingAs($user)->put(route('admin.movimentacoes.doacoes.update', $v1), [
            'qtd_fruta_um' => '2',
            'motivo_doacao' => 'V2',
            'motivo_substituicao' => 'Ajuste de quantidade',
        ])->assertRedirect();

        $v2 = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('versao')
            ->firstOrFail();

        $this->assertSame(2, (int) $v2->versao);
        $this->assertSame('2.00', (string) $v2->qtd_fruta_um);

        $v1fresh = Movimentacao::query()->whereKey($v1->id)->firstOrFail();
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1fresh->status_registro);

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_SUBSTITUICAO_VERSAO,
        ]);
    }

    public function test_versao_substituida_nao_entra_no_calculo_de_saldo(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'V1',
        ])->assertRedirect();

        $v1 = Movimentacao::query()->where('versao', 1)->firstOrFail();

        $this->actingAs($user)->put(route('admin.movimentacoes.doacoes.update', $v1), [
            'qtd_fruta_um' => '2',
            'motivo_doacao' => 'V2',
            'motivo_substituicao' => 'Ajuste',
        ])->assertRedirect();

        $ativasKg = (float) Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $empresaOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->sum('qtd_fruta_kg');

        $this->assertSame(20.0, $ativasKg);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame('80.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('4.00', (string) $estoque->preco_medio_kg);
        $this->assertSame('320.00', (string) $estoque->valor_total_acumulado);

        $v2 = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('versao')
            ->firstOrFail();

        $this->assertSame('80.00', (string) $v2->valor_total_movimentacao);
        $this->assertSame('0.00', (string) $v2->valor_nf_total);
    }

    public function test_cancelamento_administrativo_devolve_estoque_e_reprocessa_futuras(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario([
            Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D1',
        ])->assertRedirect();

        $d1 = Movimentacao::query()->orderBy('id')->firstOrFail();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D2',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.cancelar-admin', $d1), [
            'motivo' => 'Correção administrativa da primeira doação.',
        ])->assertRedirect();

        $d1fresh = Movimentacao::query()->whereKey($d1->id)->firstOrFail();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $d1fresh->status_registro);
        $this->assertNotNull($d1fresh->cancelada_em);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame('90.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('360.00', (string) $estoque->valor_total_acumulado);
        $this->assertSame('4.00', (string) $estoque->preco_medio_kg);

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN,
            'origem' => MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN,
        ]);
    }

    public function test_usuario_sem_permissao_nao_cancela_administrativamente(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D1',
        ])->assertRedirect();

        $mov = Movimentacao::query()->firstOrFail();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.cancelar-admin', $mov), [
            'motivo' => 'Tentativa sem permissão.',
        ])->assertForbidden();
    }

    public function test_motivo_cancelamento_obrigatorio(): void
    {
        $this->seedBase();
        [$empresaOrigem, , $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario([
            Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D1',
        ])->assertRedirect();

        $mov = Movimentacao::query()->firstOrFail();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.cancelar-admin', $mov), [
            'motivo' => 'ab',
        ])->assertSessionHasErrors('motivo');
    }

    public function test_rollback_transacional_quando_replay_falha(): void
    {
        $this->seedBase();
        [$empresaOrigem, $unidade, $fruta] = $this->criarCenarioDoacao();
        $user = $this->movimentacoesDoacoesUsuario([
            Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'motivo_doacao' => 'D1',
        ])->assertRedirect();

        $mov = Movimentacao::query()->firstOrFail();

        $snapshotMov = [
            'status_registro' => $mov->status_registro,
            'cancelada_em' => $mov->cancelada_em,
            'cancelada_por' => $mov->cancelada_por,
            'motivo_cancelamento' => $mov->motivo_cancelamento,
        ];

        $estoqueAntes = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $snapshotEstoque = [
            'qtd_fruta_kg' => (string) $estoqueAntes->qtd_fruta_kg,
            'valor_total_acumulado' => (string) $estoqueAntes->valor_total_acumulado,
        ];

        $countMe = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->count();

        $countHistCancel = MovimentacaoHistorico::query()
            ->where('acao', MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN)
            ->count();

        $this->mock(ReplayLinhaTempoEstoqueService::class, function ($mock): void {
            $mock->shouldReceive('reprocessarUnidadeFruta')
                ->once()
                ->andThrow(new RuntimeException('falha simulada no replay de doações'));
        });

        $service = app(CancelarDoacaoMovimentacaoAdminService::class);

        try {
            $service->executar($mov, $user, 'Simulação de falha no replay.');
            $this->fail('Esperava RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada no replay de doações', $e->getMessage());
        }

        $movFresh = Movimentacao::query()->whereKey($mov->id)->firstOrFail();
        $this->assertSame($snapshotMov['status_registro'], $movFresh->status_registro);
        $this->assertSame($snapshotMov['cancelada_em'], $movFresh->cancelada_em);
        $this->assertSame($snapshotMov['cancelada_por'], $movFresh->cancelada_por);
        $this->assertSame($snapshotMov['motivo_cancelamento'], $movFresh->motivo_cancelamento);

        $estoqueDepois = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame($snapshotEstoque['qtd_fruta_kg'], (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame($snapshotEstoque['valor_total_acumulado'], (string) $estoqueDepois->valor_total_acumulado);

        $this->assertSame($countMe, MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->count());

        $this->assertSame($countHistCancel, MovimentacaoHistorico::query()
            ->where('acao', MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN)
            ->count());
    }
}
