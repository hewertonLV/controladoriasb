<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\StatusTransferenciaOperacional;
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
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\ReconciliacaoTransferenciaService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class TransferenciaMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private function seedCategoriasEEstados(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
        ]);
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: UnidadeNegocio, 3: UnidadeNegocio, 4: Fruta}
     */
    private function criarCenarioTransferencia(): array
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

        return [$empresaOrigem, $empresaDestino, $unidadeOrigem, $unidadeDestino, $fruta];
    }

    /**
     * @return array{Empresa, UnidadeNegocio}
     */
    private function criarUnidadeDestinoComHistoricoCo(): array
    {
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $empresa = $unidade->registroCorporativo()->firstOrFail();
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '2.00',
            'status_position' => true,
        ]);

        return [$empresa, $unidade];
    }

    private function somaKgSaidasAtivasNoFrete(int $idFrete): float
    {
        return (float) Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->where('id_frete', $idFrete)
            ->sum('qtd_fruta_kg');
    }

    public function test_create_carrega_origem_e_destino_apenas_com_unidades_que_controlam_estoque(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, $unidadeDestino] = $this->criarCenarioTransferencia();
        $unidadeSemEstoque = UnidadeNegocio::factory()->create([
            'possui_estoque' => false,
            'nome' => 'UNIDADE TRANSFERENCIA SEM ESTOQUE '.uniqid(),
        ]);
        $cliente = Cliente::factory()->create([
            'razao_social' => 'CLIENTE TRANSFERENCIA SEM ESTOQUE '.uniqid(),
        ]);
        $fornecedor = Fornecedor::factory()->create([
            'razao_social' => 'FORNECEDOR TRANSFERENCIA SEM ESTOQUE '.uniqid(),
        ]);

        $html = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR]))
            ->get(route('admin.movimentacoes.transferencias.create'))
            ->assertOk()
            ->getContent();

        $origemSelect = $this->selectHtml((string) $html, 'id_empresa_origem');
        $destinoSelect = $this->selectHtml((string) $html, 'id_empresa_destino');

        foreach ([$origemSelect, $destinoSelect] as $select) {
            $selectDecodificado = html_entity_decode($select, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertStringContainsString((string) $empresaOrigem->id, $select);
            $this->assertStringContainsString((string) $empresaDestino->id, $select);
            $this->assertStringContainsString($unidadeOrigem->nome, $selectDecodificado);
            $this->assertStringContainsString($unidadeDestino->nome, $selectDecodificado);
            $this->assertStringNotContainsString($unidadeSemEstoque->nome, $selectDecodificado);
            $this->assertStringNotContainsString($cliente->razao_social, $selectDecodificado);
            $this->assertStringNotContainsString($fornecedor->razao_social, $selectDecodificado);
        }
    }

    public function test_criar_transferencia_gera_par_baixa_origem_e_credita_destino(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, $unidadeDestino, $fruta] = $this->criarCenarioTransferencia();

        $user = $this->movimentacoesTransferenciasUsuario();

        $response = $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
            'numero_nf_origem' => 'NF-1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('movimentacoes', 2);

        $saida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $entrada = Movimentacao::query()
            ->whereKey((int) $saida->pareada_movimentacao_id)
            ->firstOrFail();

        $anchor = (int) $saida->transferencia_origem_id;
        $this->assertSame($anchor, (int) $entrada->transferencia_origem_id);
        $this->assertSame(StatusMovimentacao::ID_ENTRADA, (int) $entrada->status_movimentacao_id);
        $this->assertSame(StatusTransferenciaOperacional::RECEBIDA_CONFORME->value, $entrada->status_transferencia);
        $this->assertNotNull($entrada->id_movimentacao_estoque_new);

        $estoqueOrigem = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame('80.00', (string) $estoqueOrigem->qtd_fruta_kg);
        $this->assertSame('8.00', (string) $estoqueOrigem->qtd_fruta_um);

        $estoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $this->assertSame('20.00', (string) $estoqueDestino->qtd_fruta_kg);
        $this->assertSame('2.00', (string) $estoqueDestino->qtd_fruta_um);
    }

    public function test_criar_transferencia_multi_item_gera_um_par_por_fruta(): void
    {
        $this->seedCategoriasEEstados();
        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        $fruta2 = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '0.00',
            'entrada_externo' => '0.00',
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => '5.00',
        ]);
        $estoque2 = Estoque::factory()->create([
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta2->id,
            'qtd_fruta_kg' => '50.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '6.00',
            'preco_medio_um' => '30.00',
            'valor_total_acumulado' => '300.00',
        ]);
        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque2->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta2->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '50.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '6.00',
            'preco_medio_um' => '30.00',
            'valor_total_fruta' => '300.00',
            'status_ultima_posicao' => true,
        ]);

        $this->actingAs($this->movimentacoesTransferenciasUsuario())
            ->postJson(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'itens' => [
                    ['id_fruta' => $fruta->id, 'qtd_fruta_um' => '2'],
                    ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '3'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, Movimentacao::query()->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)->count());
        $this->assertSame(2, Movimentacao::query()->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)->count());
    }

    public function test_sem_frete_valores_zerados(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
        ])->assertRedirect();

        $saida = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->firstOrFail();

        $this->assertNull($saida->id_frete);
        $this->assertSame('0.00', (string) $saida->valor_frete_kg);
        $this->assertSame('0.00', (string) $saida->valor_frete_rateio);
    }

    public function test_vincular_frete_recalcula_entrada_e_estoque_destino(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , $unidadeDestino, $fruta] = $this->criarCenarioTransferencia();
        $frete = Frete::factory()->create(['valor' => '100.00']);
        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_EDITAR,
        ]);

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '10',
        ])->assertRedirect();

        $saida = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->firstOrFail();
        $anchor = (int) $saida->transferencia_origem_id;

        $entradaAntes = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('transferencia_origem_id', $anchor)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $precoEntradaAntes = (float) $entradaAntes->preco_medio_fruta_kg;

        $this->actingAs($user)->post(
            route('admin.movimentacoes.transferencias.vincular-frete', ['transferenciaOrigem' => $anchor]),
            ['id_frete' => $frete->id],
        )->assertRedirect();

        $saida->refresh();
        $entrada = $entradaAntes->fresh();

        $this->assertSame($frete->id, (int) $saida->id_frete);
        $this->assertSame($frete->id, (int) $entrada->id_frete);
        $this->assertGreaterThan(0.0, (float) $saida->valor_frete_rateio);
        $this->assertGreaterThan($precoEntradaAntes, (float) $entrada->preco_medio_fruta_kg);

        $estoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertGreaterThan(0.0, (float) $estoqueDestino->preco_medio_kg);
    }

    public function test_detalhe_conforme_exibe_botao_cancelar_admin(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();
        $user = $this->movimentacoesTransferenciasUsuario([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN,
        ]);

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
        ])->assertRedirect();

        $anchor = (int) Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->value('transferencia_origem_id');

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $anchor]))
            ->assertOk()
            ->assertSee('Cancelar transferência', false)
            ->assertSee('Cancelar (admin)', false);
    }

    public function test_cancelar_transferencia_conforme_restitui_origem_e_destino(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, $unidadeDestino, $fruta] = $this->criarCenarioTransferencia();
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
        ])->assertRedirect();

        $saida = Movimentacao::query()->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)->firstOrFail();
        $anchor = (int) $saida->transferencia_origem_id;

        $this->actingAs($user)->post(
            route('admin.movimentacoes.transferencias.cancelar', ['transferenciaOrigem' => $anchor]),
            ['motivo_substituicao' => 'Cancelado após registro.'],
        )->assertRedirect(route('admin.movimentacoes.transferencias.index'));

        $estoqueOrigem = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();
        $estoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('100.00', (string) $estoqueOrigem->qtd_fruta_kg);
        $this->assertSame('10.00', (string) $estoqueOrigem->qtd_fruta_um);
        $this->assertSame('0.00', (string) $estoqueDestino->qtd_fruta_kg);
        $this->assertSame('0.00', (string) $estoqueDestino->qtd_fruta_um);

        $this->assertSame(
            0,
            Movimentacao::query()
                ->where('transferencia_origem_id', $anchor)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->count(),
        );

        $saidaFinal = Movimentacao::query()
            ->where('transferencia_origem_id', $anchor)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->firstOrFail();
        $entradaFinal = Movimentacao::query()
            ->where('transferencia_origem_id', $anchor)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->firstOrFail();

        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $saidaFinal->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $entradaFinal->status_registro);
        $this->assertSame(StatusTransferenciaOperacional::CANCELADA->value, $saidaFinal->status_transferencia);
        $this->assertSame(StatusTransferenciaOperacional::CANCELADA->value, $entradaFinal->status_transferencia);
    }

    public function test_sem_permissao_criar_bloqueia_store(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();

        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR]);

        $response = $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
        ]);

        $response->assertForbidden();
    }

    /** Duas transferências no mesmo frete recalculam rateio corretamente. */
    public function test_duas_transferencias_mesmo_frete_recalculam_rateio_corretamente(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        [$empresaDestino2] = $this->criarUnidadeDestinoComHistoricoCo();

        $frete = Frete::factory()->create(['valor' => '300.00']);

        Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->update([
                'qtd_fruta_kg' => '500.00',
                'qtd_fruta_um' => '50.00',
                'valor_total_acumulado' => '2500.00',
            ]);
        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->update([
                'qtd_fruta_kg' => '500.00',
                'qtd_fruta_um' => '50.00',
                'valor_total_fruta' => '2500.00',
            ]);

        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino2->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $totalKg = $this->somaKgSaidasAtivasNoFrete($frete->id);
        $this->assertSame(30.0, $totalKg);

        $valorKgEsperado = round(300.0 / 30.0, 2);
        $frete->refresh();
        $this->assertSame(number_format($valorKgEsperado, 2, '.', ''), (string) $frete->valor_fruta_kg);

        $saidas = Movimentacao::query()
            ->where('id_frete', $frete->id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $saidas);
        foreach ($saidas as $s) {
            $this->assertSame(number_format($valorKgEsperado, 2, '.', ''), (string) $s->valor_frete_kg);
        }
    }

    /** Recalcular rateio não cria novas linhas em movimentacoes_estoque na origem. */
    public function test_rateio_frete_distribui_centavos_para_fechar_valor_total(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        [$empresaDestino2] = $this->criarUnidadeDestinoComHistoricoCo();
        [$empresaDestino3] = $this->criarUnidadeDestinoComHistoricoCo();
        $frete = Frete::factory()->create(['valor' => '100.00']);

        Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->update([
                'qtd_fruta_kg' => '500.00',
                'qtd_fruta_um' => '50.00',
                'valor_total_acumulado' => '2500.00',
            ]);
        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->update([
                'qtd_fruta_kg' => '500.00',
                'qtd_fruta_um' => '50.00',
                'valor_total_fruta' => '2500.00',
            ]);

        $user = $this->movimentacoesTransferenciasUsuario();

        foreach ([$empresaDestino, $empresaDestino2, $empresaDestino3] as $destino) {
            $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $destino->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '1',
                'id_frete' => $frete->id,
            ])->assertRedirect();
        }

        $saidas = Movimentacao::query()
            ->where('id_frete', $frete->id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $saidas);
        $this->assertSame('100.00', number_format((float) $saidas->sum('valor_frete_rateio'), 2, '.', ''));
        $this->assertSame(['33.34', '33.33', '33.33'], $saidas->pluck('valor_frete_rateio')->map(fn ($valor): string => (string) $valor)->all());
    }

    /** Recalcular rateio não cria novas linhas em movimentacoes_estoque na origem. */
    public function test_alteracao_rateio_frete_nao_duplica_movimentacoes_estoque(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        [$empresaDestino2] = $this->criarUnidadeDestinoComHistoricoCo();
        $frete = Frete::factory()->create(['valor' => '200.00']);

        Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->update([
                'qtd_fruta_kg' => '300.00',
                'qtd_fruta_um' => '30.00',
                'valor_total_acumulado' => '1500.00',
            ]);
        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->update([
                'qtd_fruta_kg' => '300.00',
                'qtd_fruta_um' => '30.00',
                'valor_total_fruta' => '1500.00',
            ]);

        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $countAposPrimeira = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->count();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino2->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $countAposSegunda = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->count();

        $this->assertSame($countAposPrimeira + 1, $countAposSegunda);

        $antesReconciliar = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->count();

        app(ReconciliacaoTransferenciaService::class)->recalcularRateioFreteParaTransferencias($frete->id);
        app(ReconciliacaoTransferenciaService::class)->recalcularRateioFreteParaTransferencias($frete->id);

        $this->assertSame(
            $antesReconciliar,
            MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->count(),
        );
    }

    public function test_transferencia_cancelada_nao_entra_no_calculo_de_rateio_de_frete(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        [$empresaDestino2] = $this->criarUnidadeDestinoComHistoricoCo();
        $frete = Frete::factory()->create(['valor' => '200.00']);

        Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->update([
                'qtd_fruta_kg' => '400.00',
                'qtd_fruta_um' => '40.00',
                'valor_total_acumulado' => '2000.00',
            ]);
        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->update([
                'qtd_fruta_kg' => '400.00',
                'qtd_fruta_um' => '40.00',
                'valor_total_fruta' => '2000.00',
            ]);

        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino2->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $anchorCancelar = (int) Movimentacao::query()
            ->where('id_empresa_destino', $empresaDestino->id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->value('transferencia_origem_id');

        $this->actingAs($user)->post(
            route('admin.movimentacoes.transferencias.cancelar', ['transferenciaOrigem' => $anchorCancelar]),
            ['motivo_substituicao' => 'Cancela primeira cadeia.'],
        )->assertRedirect();

        $this->assertSame(10.0, $this->somaKgSaidasAtivasNoFrete($frete->id));

        $frete->refresh();
        $this->assertSame('20.00', (string) $frete->valor_fruta_kg);
    }

    public function test_reconciliacao_frete_considera_apenas_saidas_ativas(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();
        $frete = Frete::factory()->create(['valor' => '100.00']);

        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
            'id_frete' => $frete->id,
        ])->assertRedirect();

        $saidaAntiga = Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        $saidaAntiga->forceFill([
            'status_registro' => MovimentacaoStatusRegistro::SUBSTITUIDO->value,
            'qtd_fruta_kg' => '999.00',
            'qtd_fruta_um' => '99.90',
        ])->saveQuietly();

        app(ReconciliacaoTransferenciaService::class)->recalcularRateioFreteParaTransferencias($frete->id);

        $frete->refresh();
        $this->assertSame('0.00', (string) $frete->valor_fruta_kg);
    }

    public function test_somente_uma_movimentacao_estoque_com_ultima_posicao_na_origem(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2',
        ])->assertRedirect();

        $this->assertSame(
            1,
            MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->where('status_ultima_posicao', true)
                ->count(),
        );
    }

    public function test_rollback_total_quando_falha_apos_baixa_na_origem(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $fruta] = $this->criarCenarioTransferencia();

        $payload = [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
        ];

        $estoqueAntes = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        try {
            DB::transaction(function () use ($payload): void {
                app(TransferenciaMovimentacaoService::class)->criarTransferencia($payload);
                throw new \RuntimeException('simulated_abort');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated_abort', $e->getMessage());
        }

        $this->assertDatabaseCount('movimentacoes', 0);

        $estoqueDepois = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame((string) $estoqueAntes->qtd_fruta_um, (string) $estoqueDepois->qtd_fruta_um);
    }

    public function test_usuario_sem_permissao_cancelar_nao_cancela(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();
        $completo = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($completo)->post(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '1',
        ])->assertRedirect();

        $anchor = (int) Movimentacao::query()
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->value('transferencia_origem_id');

        $semCancelar = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_EDITAR,
        ]);

        $this->actingAs($semCancelar)->post(
            route('admin.movimentacoes.transferencias.cancelar', ['transferenciaOrigem' => $anchor]),
            [],
        )->assertForbidden();
    }

    public function test_nao_permite_origem_igual_ao_destino(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)
            ->from(route('admin.movimentacoes.transferencias.create'))
            ->post(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaOrigem->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '1',
            ])
            ->assertSessionHasErrors('id_empresa_destino');
    }

    public function test_permite_transferencia_com_saldo_negativo_quando_origem_tem_registro_de_estoque(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, , , $fruta] = $this->criarCenarioTransferencia();

        $par = app(TransferenciaMovimentacaoService::class)->criarTransferencia([
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '999',
        ]);

        $this->assertSame('-989.00', (string) $par['saida']->fresh()->saldo_estoque_fruta_um);
    }

    public function test_nao_permite_transferencia_quando_origem_nunca_recebeu_produto(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino, $unidadeOrigem, , $frutaComEstoque] = $this->criarCenarioTransferencia();
        $frutaSemEstoque = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->assertDatabaseMissing('estoques', [
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $frutaSemEstoque->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('admin.movimentacoes.transferencias.create'))
            ->post(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_fruta' => $frutaSemEstoque->id,
                'qtd_fruta_um' => '1',
            ]);

        $response
            ->assertRedirect(route('admin.movimentacoes.transferencias.create'))
            ->assertSessionHasErrors('id_fruta');

        $this->assertDatabaseMissing('estoques', [
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $frutaSemEstoque->id,
        ]);

        $this->assertDatabaseHas('estoques', [
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $frutaComEstoque->id,
        ]);
    }

    public function test_retorna_422_json_quando_origem_nunca_recebeu_produto(): void
    {
        $this->seedCategoriasEEstados();

        [$empresaOrigem, $empresaDestino] = $this->criarCenarioTransferencia();
        $frutaSemEstoque = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);
        $user = $this->movimentacoesTransferenciasUsuario();

        $this
            ->actingAs($user)
            ->postJson(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_fruta' => $frutaSemEstoque->id,
                'qtd_fruta_um' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_fruta');
    }

    private function selectHtml(string $html, string $id): string
    {
        $pattern = sprintf('/<select[^>]*id="%s"[^>]*>.*?<\/select>/s', preg_quote($id, '/'));

        return preg_match($pattern, $html, $matches) === 1 ? $matches[0] : '';
    }
}
