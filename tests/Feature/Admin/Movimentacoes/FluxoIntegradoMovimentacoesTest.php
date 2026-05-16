<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
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
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class FluxoIntegradoMovimentacoesTest extends TestCase
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

    public function test_cenario_1_compra_mais_doacao_preserva_preco_medio_e_valor_economico(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $doacao = $this->registrarDoacao($c['empresa_a'], $c['fruta'], '2');

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertSame('100.00', (string) $doacao->valor_total_movimentacao);
        $this->assertSame('0.00', (string) $doacao->valor_nf_total);
        $this->assertUltimaPosicaoBateComEstoque($c['unidade_a'], $c['fruta']);
        $this->assertSomenteAtivasEntramNoCalculo($c['fruta']);
    }

    public function test_cenario_2_duas_compras_mais_doacao_usa_preco_medio_consolidado(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $this->registrarCompra($c, $c['frete_compra'], '10', '700,00');

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '200.00', '20.00', '6.00', '60.00', '1200.00');

        $doacao = $this->registrarDoacao($c['empresa_a'], $c['fruta'], '5');

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '150.00', '15.00', '6.00', '60.00', '900.00');
        $this->assertSame('300.00', (string) $doacao->valor_total_movimentacao);
        $this->assertUltimaPosicaoBateComEstoque($c['unidade_a'], $c['fruta']);
    }

    public function test_cenario_3_compra_transferencia_conforme_baixa_origem_e_entrada_so_apos_recebimento(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $saida = $this->registrarTransferencia($c, '4');
        $anchor = (int) $saida->transferencia_origem_id;

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '60.00', '6.00', '5.00', '50.00', '300.00');
        $this->assertEstoque($c['unidade_b'], $c['fruta'], '0.00', '0.00', '0.00', '0.00', '0.00');

        $entradaPendente = Movimentacao::query()
            ->where('transferencia_origem_id', $anchor)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->firstOrFail();
        $this->assertNull($entradaPendente->id_movimentacao_estoque_new);
        $this->assertSame(StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value, $entradaPendente->status_transferencia);

        $this->confirmarTransferenciaConforme($anchor, '4');

        $entrada = Movimentacao::query()
            ->where('transferencia_origem_id', $anchor)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->firstOrFail();

        $this->assertSame(StatusTransferenciaOperacional::RECEBIDA_CONFORME->value, $entrada->status_transferencia);
        $this->assertNotNull($entrada->id_movimentacao_estoque_new);
        $this->assertEstoque($c['unidade_b'], $c['fruta'], '40.00', '4.00', '5.00', '50.00', '200.00');
        $this->assertUltimaPosicaoBateComEstoque($c['unidade_a'], $c['fruta']);
        $this->assertUltimaPosicaoBateComEstoque($c['unidade_b'], $c['fruta']);
    }

    public function test_cenario_4_transferencia_divergente_cancelada_devolve_origem_e_nao_afeta_destino(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $saida = $this->registrarTransferencia($c, '3');
        $anchor = (int) $saida->transferencia_origem_id;

        $this->marcarTransferenciaDivergente($anchor, '2');
        $this->cancelarTransferencia($anchor);

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '100.00', '10.00', '5.00', '50.00', '500.00');
        $this->assertEstoque($c['unidade_b'], $c['fruta'], '0.00', '0.00', '0.00', '0.00', '0.00');
        $this->assertSame(0, $this->movimentacoesAtivasTransferencia($anchor)->count());

        $canceladas = Movimentacao::query()->where('transferencia_origem_id', $anchor)->get();
        $this->assertCount(2, $canceladas);
        foreach ($canceladas as $m) {
            $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $m->status_registro);
            $this->assertSame(StatusTransferenciaOperacional::CANCELADA->value, $m->status_transferencia);
        }

        $this->assertUltimaPosicaoBateComEstoque($c['unidade_a'], $c['fruta']);
        $this->assertNoMaximoUmaUltimaPosicao($c['unidade_b'], $c['fruta']);
    }

    public function test_cenario_5_transferencia_confirmada_mais_doacao_no_destino(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $saida = $this->registrarTransferencia($c, '4');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '4');

        $doacao = $this->registrarDoacao($c['empresa_b'], $c['fruta'], '1');

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '60.00', '6.00', '5.00', '50.00', '300.00');
        $this->assertEstoque($c['unidade_b'], $c['fruta'], '30.00', '3.00', '5.00', '50.00', '150.00');
        $this->assertSame('50.00', (string) $doacao->valor_total_movimentacao);
        $this->assertSame('5.00', (string) $doacao->preco_medio_fruta_kg);
    }

    public function test_cenario_6_cancelamento_de_compra_com_doacao_futura_reprocessa_toda_linha(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $segundaCompra = $this->registrarCompra($c, $c['frete_compra'], '10', '700,00');
        $doacao = $this->registrarDoacao($c['empresa_a'], $c['fruta'], '5');

        $this->cancelarCompraAdmin($segundaCompra);

        $doacao->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $segundaCompra->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $doacao->status_registro);
        $this->assertEstoque($c['unidade_a'], $c['fruta'], '50.00', '5.00', '5.00', '50.00', '250.00');
        $this->assertSame('250.00', (string) $doacao->valor_total_movimentacao);
        $this->assertUltimaPosicaoBateComEstoque($c['unidade_a'], $c['fruta']);
    }

    public function test_cenario_7_cancelamento_de_doacao_com_compra_futura_reprocessa_tudo(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $doacao = $this->registrarDoacao($c['empresa_a'], $c['fruta'], '2');
        $this->registrarCompra($c, $c['frete_compra'], '10', '700,00');

        $this->cancelarDoacaoAdmin($doacao);

        $doacao->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $doacao->status_registro);
        $this->assertEstoque($c['unidade_a'], $c['fruta'], '200.00', '20.00', '6.00', '60.00', '1200.00');
        $this->assertSame(0, Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('id_fruta', $c['fruta']->id)
            ->count());
    }

    public function test_cenario_8_versionamento_de_compra_nao_duplica_calculo_antes_da_doacao(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $compraV1 = $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $this->atualizarCompra($compraV1, '600,00');
        $doacao = $this->registrarDoacao($c['empresa_a'], $c['fruta'], '2');

        $compraV1->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $compraV1->status_registro);
        $this->assertSame(1, Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_fruta', $c['fruta']->id)
            ->count());
        $this->assertEstoque($c['unidade_a'], $c['fruta'], '80.00', '8.00', '6.00', '60.00', '480.00');
        $this->assertSame('120.00', (string) $doacao->valor_total_movimentacao);
        $this->assertSame('6.00', (string) $doacao->preco_medio_fruta_kg);
    }

    public function test_cenario_9_frete_de_compra_e_transferencia_tem_rateios_independentes(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase(valorFreteCompra: '100,00', valorFreteTransferencia: '80,00');

        $compra = $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $saida = $this->registrarTransferencia($c, '4', $c['frete_transferencia']);
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '4');

        $compra->refresh();
        $saida->refresh();
        $entrada = Movimentacao::query()
            ->where('transferencia_origem_id', $saida->transferencia_origem_id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->firstOrFail();

        $this->assertSame((int) $c['frete_compra']->id, (int) $compra->id_frete);
        $this->assertSame('1.00', (string) $compra->valor_frete_kg);
        $this->assertSame((int) $c['frete_transferencia']->id, (int) $saida->id_frete);
        $this->assertSame('2.00', (string) $saida->valor_frete_kg);
        $this->assertSame('2.00', (string) $entrada->valor_frete_kg);

        $this->assertEstoque($c['unidade_a'], $c['fruta'], '60.00', '6.00', '6.00', '60.00', '360.00');
        $this->assertEstoque($c['unidade_b'], $c['fruta'], '40.00', '4.00', '8.00', '80.00', '320.00');
    }

    public function test_permissoes_bloqueiam_acoes_criticas_integradas(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $semPermissao = $this->userWithoutEmpresaPermissions();

        $this->actingAs($semPermissao)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $c['empresa_fornecedor']->id,
            'id_empresa_destino' => $c['empresa_a']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '10',
            'valor_nf_total' => '500,00',
            'id_frete' => $c['frete_compra']->id,
        ])->assertForbidden();

        $compra = $this->registrarCompra($c, $c['frete_compra'], '10', '500,00');
        $this->actingAs($this->movimentacoesComprasUsuario())
            ->postJson(route('admin.movimentacoes.compras.cancelar-admin', $compra), [
                'motivo' => 'Tentativa sem permissão admin.',
            ])
            ->assertForbidden();

        $saida = $this->registrarTransferencia($c, '2');
        $semReceber = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR,
        ]);
        $this->actingAs($semReceber)->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', (int) $saida->transferencia_origem_id),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::CONFORME->value,
                'qtd_recebida_um' => '2',
            ],
        )->assertForbidden();
    }

    /**
     * @return array{
     *     empresa_fornecedor: Empresa,
     *     empresa_a: Empresa,
     *     empresa_b: Empresa,
     *     unidade_a: UnidadeNegocio,
     *     unidade_b: UnidadeNegocio,
     *     fruta: Fruta,
     *     frete_compra: Frete,
     *     frete_transferencia: Frete,
     * }
     */
    private function cenarioBase(string $valorFreteCompra = '0', string $valorFreteTransferencia = '0'): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();

        $unidadeA = $this->criarUnidadeComCustoZero(Estado::ID_PERNAMBUCO);
        $unidadeB = $this->criarUnidadeComCustoZero(Estado::ID_PERNAMBUCO);

        $fruta = Fruta::factory()->create([
            'kg_por_unidade_medicao' => 10,
            'icms_na_compra' => 0,
            'icms_ex_compra' => 0,
            'um_icms' => FrutaUmIcms::KG->value,
        ]);

        return [
            'empresa_fornecedor' => $empresaFornecedor,
            'empresa_a' => $unidadeA->registroCorporativo()->firstOrFail(),
            'empresa_b' => $unidadeB->registroCorporativo()->firstOrFail(),
            'unidade_a' => $unidadeA,
            'unidade_b' => $unidadeB,
            'fruta' => $fruta,
            'frete_compra' => $this->criarFrete($valorFreteCompra),
            'frete_transferencia' => $this->criarFrete($valorFreteTransferencia),
        ];
    }

    private function criarUnidadeComCustoZero(int $estadoId): UnidadeNegocio
    {
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => $estadoId,
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

        return $unidade;
    }

    private function criarFrete(string $valor): Frete
    {
        return Frete::factory()->create([
            'valor' => $valor,
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompra(array $cenario, Frete $frete, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $user = $this->movimentacoesComprasUsuario();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_a']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $frete->id,
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_frete', $frete->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function atualizarCompra(Movimentacao $compra, string $valorNfTotal): Movimentacao
    {
        $user = $this->movimentacoesComprasUsuario();

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $compra), [
            'valor_nf_total' => $valorNfTotal,
            'motivo_substituicao' => 'Correção integrada da NF.',
        ])->assertOk();

        return Movimentacao::query()->findOrFail((int) $compra->fresh()->substituida_por_id);
    }

    private function registrarDoacao(Empresa $empresaOrigem, Fruta $fruta, string $qtdUm): Movimentacao
    {
        $user = $this->movimentacoesDoacoesUsuario();

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'motivo_doacao' => 'Doação integrada de teste',
        ])->assertRedirect();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarTransferencia(array $cenario, string $qtdUm, ?Frete $frete = null): Movimentacao
    {
        $user = $this->movimentacoesTransferenciasUsuario();
        $payload = [
            'id_empresa_origem' => $cenario['empresa_a']->id,
            'id_empresa_destino' => $cenario['empresa_b']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
        ];

        if ($frete !== null) {
            $payload['id_frete'] = $frete->id;
        }

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), $payload)
            ->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function confirmarTransferenciaConforme(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::CONFORME->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'numero_nf_destino' => 'NF-D-INT',
            ],
        )->assertOk();
    }

    private function marcarTransferenciaDivergente(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::DIVERGENTE->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'observacao_recebimento' => 'Divergência integrada de teste.',
            ],
        )->assertOk();
    }

    private function cancelarTransferencia(int $transferenciaOrigemId): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->post(
            route('admin.movimentacoes.transferencias.cancelar', $transferenciaOrigemId),
            ['motivo_substituicao' => 'Cancelamento integrado de teste.'],
        )->assertRedirect();
    }

    private function cancelarCompraAdmin(Movimentacao $compra): void
    {
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $compra), [
            'motivo' => 'Cancelamento administrativo integrado de compra.',
        ])->assertOk();
    }

    private function cancelarDoacaoAdmin(Movimentacao $doacao): void
    {
        $admin = $this->movimentacoesDoacoesUsuario([
            Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
        ]);

        $this->actingAs($admin)->post(route('admin.movimentacoes.doacoes.cancelar-admin', $doacao), [
            'motivo' => 'Cancelamento administrativo integrado de doação.',
        ])->assertRedirect();

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN,
            'origem' => MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN,
        ]);
    }

    private function assertEstoque(
        UnidadeNegocio $unidade,
        Fruta $fruta,
        string $kg,
        string $um,
        string $precoKg,
        string $precoUm,
        string $valorTotal,
    ): void {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame($kg, (string) $estoque->qtd_fruta_kg);
        $this->assertSame($um, (string) $estoque->qtd_fruta_um);
        $this->assertSame($precoKg, (string) $estoque->preco_medio_kg);
        $this->assertSame($precoUm, (string) $estoque->preco_medio_um);
        $this->assertSame($valorTotal, (string) $estoque->valor_total_acumulado);
    }

    private function assertUltimaPosicaoBateComEstoque(UnidadeNegocio $unidade, Fruta $fruta): void
    {
        $this->assertNoMaximoUmaUltimaPosicao($unidade, $fruta);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $me = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->firstOrFail();

        $this->assertSame((string) $estoque->qtd_fruta_kg, (string) $me->qtd_fruta_kg);
        $this->assertSame((string) $estoque->qtd_fruta_um, (string) $me->qtd_fruta_um);
        $this->assertSame((string) $estoque->preco_medio_kg, (string) $me->preco_medio_kg);
        $this->assertSame((string) $estoque->preco_medio_um, (string) $me->preco_medio_um);
        $this->assertSame((string) $estoque->valor_total_acumulado, (string) $me->valor_total_fruta);
    }

    private function assertNoMaximoUmaUltimaPosicao(UnidadeNegocio $unidade, Fruta $fruta): void
    {
        $this->assertLessThanOrEqual(1, MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->count());
    }

    private function assertSomenteAtivasEntramNoCalculo(Fruta $fruta): void
    {
        $this->assertSame(0, Movimentacao::query()
            ->where('id_fruta', $fruta->id)
            ->whereIn('status_registro', [
                MovimentacaoStatusRegistro::CANCELADO->value,
                MovimentacaoStatusRegistro::SUBSTITUIDO->value,
            ])
            ->vigentesParaCalculo()
            ->count());
    }

    private function movimentacoesAtivasTransferencia(int $transferenciaOrigemId)
    {
        return Movimentacao::query()
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->get();
    }
}
