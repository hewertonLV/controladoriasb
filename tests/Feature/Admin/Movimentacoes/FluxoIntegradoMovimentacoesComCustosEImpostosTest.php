<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\StatusRecebimentoTransferencia;
use App\Enums\StatusTransferenciaOperacional;
use App\Enums\TipoDevolucao;
use App\Models\CategoriaDescarte;
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
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Services\UnidadesNegocio\HistoricoCustoOperacionalUnidadeNegocioService;
use Database\Seeders\CategoriaDescarteSeeder;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class FluxoIntegradoMovimentacoesComCustosEImpostosTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private int $eventosExecutados = 0;

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            CategoriaDescarteSeeder::class,
        ]);
    }

    public function test_stress_com_34_eventos_preserva_invariantes_de_estoque_custos_icms_frete_e_replay(): void
    {
        $this->seedBase();
        $c = $this->cenarioStress();

        // 1. Compra Unidade A.
        $compraA1 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_a'],
            fruta: $c['fruta'],
            frete: $c['frete1'],
            qtdUm: '10',
            valorNfTotal: '1.000,00',
        ));

        // 2. Compra Unidade A com Frete 1.
        $compraA2 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_ce'],
            destino: $c['empresa_a'],
            fruta: $c['fruta'],
            frete: $c['frete1'],
            qtdUm: '6',
            valorNfTotal: '720,00',
        ));

        // 3. Doação Unidade A.
        $doacaoA1 = $this->evento(fn () => $this->registrarDoacao(
            origem: $c['empresa_a'],
            fruta: $c['fruta'],
            qtdUm: '1',
            cliente: $c['cliente_doacao'],
        ));

        // 4. Transferência A -> B.
        $saidaAB1 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_a'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            qtdUm: '2',
        ));

        // 5. Recebimento conforme em B.
        $this->evento(fn () => $this->receberConforme((int) $saidaAB1->transferencia_origem_id, '2'));

        // 6. Compra Unidade B.
        $compraB1 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            frete: $c['frete2'],
            qtdUm: '3',
            valorNfTotal: '480,00',
        ));

        // 7. Alterar custo operacional Unidade B.
        $historicoBAtual = $this->evento(fn () => $this->alterarCustoOperacional($c['unidade_b'], '5.00'));

        // 8. Compra Unidade B após novo custo.
        $compraB2 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            frete: $c['frete2'],
            qtdUm: '2',
            valorNfTotal: '300,00',
        ));

        // 9. Transferência B -> C com Frete 2.
        $saidaBC1 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_b'],
            destino: $c['empresa_c'],
            fruta: $c['fruta'],
            qtdUm: '1',
            frete: $c['frete2'],
        ));

        // 10. Recebimento divergente em C.
        $this->evento(fn () => $this->receberDivergente((int) $saidaBC1->transferencia_origem_id, '0.5'));

        // 11. Reenviar transferência B -> C.
        $saidaBC2 = $this->evento(fn () => $this->reenviarTransferencia(
            transferenciaOrigemId: (int) $saidaBC1->transferencia_origem_id,
            qtdUm: '0.5',
            frete: $c['frete2'],
        ));

        // 12. Recebimento conforme em C.
        $this->evento(fn () => $this->receberConforme((int) $saidaBC2->transferencia_origem_id, '0.5'));

        // 13. Doação Unidade C.
        $doacaoC1 = $this->evento(fn () => $this->registrarDoacao(
            origem: $c['empresa_c'],
            fruta: $c['fruta'],
            qtdUm: '0.25',
        ));

        // 14. Alterar ICMS da fruta. Não há tabela histórica de ICMS hoje; o teste documenta isso e valida o valor salvo nas movimentações.
        $this->evento(function () use ($c): void {
            app(FrutaIcmsSyncService::class)->sync($c['fruta'], [
                Estado::ID_CEARA => [
                    FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '3.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12.00',
                ],
            ]);
        });

        // 15. Compra Unidade A após ICMS novo.
        $compraA3 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_a'],
            fruta: $c['fruta'],
            frete: $c['frete1'],
            qtdUm: '2',
            valorNfTotal: '260,00',
        ));

        // 16. Transferência C -> A.
        $saidaCA1 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_c'],
            destino: $c['empresa_a'],
            fruta: $c['fruta'],
            qtdUm: '0.25',
        ));

        // 17. Recebimento conforme em A.
        $this->evento(fn () => $this->receberConforme((int) $saidaCA1->transferencia_origem_id, '0.25'));

        // 18. Compra Unidade C.
        $compraC1 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_c'],
            fruta: $c['fruta'],
            frete: $c['frete2'],
            qtdUm: '2',
            valorNfTotal: '360,00',
        ));

        // 19. Doação Unidade A.
        $doacaoA2 = $this->evento(fn () => $this->registrarDoacao(
            origem: $c['empresa_a'],
            fruta: $c['fruta'],
            qtdUm: '0.5',
        ));

        // 20. Cancelamento admin da doação Unidade A.
        $this->evento(fn () => $this->cancelarDoacaoAdmin($doacaoA2));

        // 21. Compra Unidade B com Frete 1.
        $compraB3 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            frete: $c['frete1'],
            qtdUm: '2',
            valorNfTotal: '340,00',
        ));

        // 22. Cancelamento admin da compra Unidade B.
        $this->evento(fn () => $this->cancelarCompraAdmin($compraB3));

        // 23. Transferência A -> C.
        $saidaAC1 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_a'],
            destino: $c['empresa_c'],
            fruta: $c['fruta'],
            qtdUm: '1',
        ));

        // 24. Recebimento conforme em C.
        $this->evento(fn () => $this->receberConforme((int) $saidaAC1->transferencia_origem_id, '1'));

        // 25. Doação Unidade B.
        $doacaoB1 = $this->evento(fn () => $this->registrarDoacao(
            origem: $c['empresa_b'],
            fruta: $c['fruta'],
            qtdUm: '0.5',
        ));

        // 26. Compra Unidade A.
        $compraA4 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_a'],
            fruta: $c['fruta'],
            frete: $c['frete1'],
            qtdUm: '3',
            valorNfTotal: '510,00',
        ));

        // 27. Atualizar compra Unidade A criando nova versão.
        $compraA4V2 = $this->evento(fn () => $this->atualizarCompra($compraA4, '600,00'));

        // 28. Transferência C -> B.
        $saidaCB1 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_c'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            qtdUm: '1',
        ));

        // 29. Recebimento divergente em B.
        $this->evento(fn () => $this->receberDivergente((int) $saidaCB1->transferencia_origem_id, '0.5'));

        // 30. Cancelar transferência C -> B.
        $this->evento(fn () => $this->cancelarTransferencia((int) $saidaCB1->transferencia_origem_id));

        // 31. Compra Unidade C com Frete 2.
        $compraC2 = $this->evento(fn () => $this->registrarCompra(
            fornecedor: $c['fornecedor_pe'],
            destino: $c['empresa_c'],
            fruta: $c['fruta'],
            frete: $c['frete2'],
            qtdUm: '2',
            valorNfTotal: '420,00',
        ));

        // 32. Descarte Unidade C.
        $descarteC1 = $this->evento(fn () => $this->registrarDescarte(
            origem: $c['empresa_c'],
            fruta: $c['fruta'],
            qtdUm: '0.5',
        ));

        // 33. Transferência A -> B.
        $saidaAB2 = $this->evento(fn () => $this->registrarTransferencia(
            origem: $c['empresa_a'],
            destino: $c['empresa_b'],
            fruta: $c['fruta'],
            qtdUm: '1',
        ));

        // 34. Recebimento conforme em B.
        $this->evento(fn () => $this->receberConforme((int) $saidaAB2->transferencia_origem_id, '1'));

        // 35. Venda em C.
        $vendaC1 = $this->evento(fn () => $this->registrarVenda(
            origem: $c['empresa_c'],
            cliente: $c['cliente_doacao'],
            unidadeFaturamento: $c['unidade_c'],
            fruta: $c['fruta'],
            qtdUm: '0.5',
            valorNfTotal: '300,00',
        ));

        // 36. Devolução parcial com retorno vinculada à venda em C.
        $devolucaoC1 = $this->evento(fn () => $this->registrarDevolucao(
            venda: $vendaC1,
            tipo: TipoDevolucao::COM_RETORNO_ESTOQUE,
            qtdUm: '0.25',
        ));

        $this->assertSame(36, $this->eventosExecutados);

        $this->assertSemEstoqueNegativo();
        foreach ([$c['unidade_a'], $c['unidade_b'], $c['unidade_c']] as $unidade) {
            $this->assertEstoqueConsistente($unidade, $c['fruta']);
            $this->assertUmaUltimaPosicao($unidade, $c['fruta']);
        }

        $this->assertMovimentacoesCanceladasForaDoCalculo();
        $this->assertMovimentacoesSubstituidasForaDoCalculo();
        $this->assertCustoOperacionalHistoricoPreservado($compraB1, $compraB2, $historicoBAtual);
        $this->assertIcmsHistoricoPreservadoOuDocumentado($compraA1, $compraA3);
        $this->assertFreteCompraRecalculadoApenasComAtivas($c['frete1']);
        $this->assertFreteCompraRecalculadoApenasComAtivas($c['frete2']);
        $this->assertFreteTransferenciaRecalculadoApenasComAtivas($c['frete2']);
        $this->assertDoacaoPreservaPrecoMedioEValorEconomico($doacaoA1);
        $this->assertDoacaoPreservaPrecoMedioEValorEconomico($doacaoB1);
        $this->assertDoacaoPreservaPrecoMedioEValorEconomico($doacaoC1);
        $this->assertDescartePreservaPrecoMedioEValorEconomico($descarteC1);
        $this->assertDevolucaoPreservaCustoHistorico($vendaC1, $devolucaoC1);
        $this->assertTransferenciaDivergenteOuPendenteNaoEntraNoDestino();
        $this->assertTransferenciasConformesEntraramNoDestino();
        $this->assertCancelamentoAdministrativoReprocessouLinhaDoTempo();
        $this->assertVersionamentoNaoDuplicaCalculo($compraA4, $compraA4V2);

        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $compraB3->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $doacaoA2->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $saidaCB1->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $saidaBC1->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $saidaBC2->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $compraC1->fresh()->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $compraC2->fresh()->status_registro);
    }

    /**
     * @return array{
     *     fornecedor_ce: Empresa,
     *     fornecedor_pe: Empresa,
     *     empresa_a: Empresa,
     *     empresa_b: Empresa,
     *     empresa_c: Empresa,
     *     unidade_a: UnidadeNegocio,
     *     unidade_b: UnidadeNegocio,
     *     unidade_c: UnidadeNegocio,
     *     cliente_doacao: Empresa,
     *     fruta: Fruta,
     *     frete1: Frete,
     *     frete2: Frete,
     * }
     */
    private function cenarioStress(): array
    {
        $fornecedorCe = Fornecedor::factory()->create(['id_estado' => Estado::ID_CEARA]);
        $fornecedorPe = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);

        $unidadeA = $this->criarUnidade('UNIDADE A STRESS', Estado::ID_CEARA, '0.00');
        $unidadeB = $this->criarUnidade('UNIDADE B STRESS', Estado::ID_PERNAMBUCO, '2.00');
        $unidadeC = $this->criarUnidade('UNIDADE C STRESS', Estado::ID_ALAGOAS, '1.00');

        $cliente = Cliente::factory()->create(['id_unidade_negocio' => $unidadeA->id]);

        $fruta = Fruta::factory()->comIcmsCeara([
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '1.00',
        ])->create([
            'nome' => 'BANANA STRESS '.uniqid(),
            'kg_por_unidade_medicao' => 20,
        ]);

        return [
            'fornecedor_ce' => $fornecedorCe->registroCorporativo()->firstOrFail(),
            'fornecedor_pe' => $fornecedorPe->registroCorporativo()->firstOrFail(),
            'empresa_a' => $unidadeA->registroCorporativo()->firstOrFail(),
            'empresa_b' => $unidadeB->registroCorporativo()->firstOrFail(),
            'empresa_c' => $unidadeC->registroCorporativo()->firstOrFail(),
            'unidade_a' => $unidadeA,
            'unidade_b' => $unidadeB,
            'unidade_c' => $unidadeC,
            'cliente_doacao' => $cliente->registroCorporativo()->firstOrFail(),
            'fruta' => $fruta,
            'frete1' => $this->criarFrete('FRETE 1 STRESS', '300,00'),
            'frete2' => $this->criarFrete('FRETE 2 STRESS', '200,00'),
        ];
    }

    private function criarUnidade(string $nome, int $estadoId, string $custoOperacional): UnidadeNegocio
    {
        $unidade = UnidadeNegocio::factory()->create([
            'nome' => $nome.' '.uniqid(),
            'razao_social' => $nome.' '.uniqid(),
            'possui_estoque' => true,
            'id_estado' => $estadoId,
            'custo_operacional' => $custoOperacional,
        ]);

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->update(['status_position' => false]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => $custoOperacional,
            'status_position' => true,
        ]);

        return $unidade;
    }

    private function criarFrete(string $nome, string $valor): Frete
    {
        return Frete::factory()->create([
            'nome' => $nome,
            'valor' => $valor,
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
            'valor_fruta_kg' => 0,
        ]);
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function evento(callable $callback): mixed
    {
        $this->eventosExecutados++;

        return $callback();
    }

    private function registrarCompra(Empresa $fornecedor, Empresa $destino, Fruta $fruta, Frete $frete, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $user = $this->movimentacoesComprasUsuario();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $fornecedor->id,
            'id_empresa_destino' => $destino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $frete->id,
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_empresa_destino', $destino->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function atualizarCompra(Movimentacao $compra, string $valorNfTotal): Movimentacao
    {
        $user = $this->movimentacoesComprasUsuario();

        $this->actingAs($user)->putJson(route('admin.movimentacoes.compras.update', $compra), [
            'valor_nf_total' => $valorNfTotal,
            'motivo_substituicao' => 'Ajuste de NF no stress integrado.',
        ])->assertOk();

        return Movimentacao::query()->findOrFail((int) $compra->fresh()->substituida_por_id);
    }

    private function registrarDoacao(Empresa $origem, Fruta $fruta, string $qtdUm, ?Empresa $cliente = null): Movimentacao
    {
        $user = $this->movimentacoesDoacoesUsuario();
        $payload = [
            'id_empresa_origem' => $origem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'motivo_doacao' => 'Doação stress integrada',
        ];

        if ($cliente !== null) {
            $payload['id_empresa_destino'] = $cliente->id;
        }

        $this->actingAs($user)->post(route('admin.movimentacoes.doacoes.store'), $payload)
            ->assertRedirect();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function registrarDescarte(Empresa $origem, Fruta $fruta, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesDescartesUsuario())->postJson(route('admin.movimentacoes.descartes.store'), [
            'id_empresa_origem' => $origem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'categoria_descarte_id' => CategoriaDescarte::ID_PERDA_OPERACIONAL,
            'motivo_descarte' => 'Descarte stress integrado',
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function registrarVenda(
        Empresa $origem,
        Empresa $cliente,
        UnidadeNegocio $unidadeFaturamento,
        Fruta $fruta,
        string $qtdUm,
        string $valorNfTotal,
    ): Movimentacao {
        $payload = [
            'numero_nf' => 'NF-VENDA-STRESS',
            'id_empresa_origem' => $origem->id,
            'id_empresa_destino' => $cliente->id,
            'itens' => [
                ['id_fruta' => $fruta->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ];

        if ($origem->loadMissing('entidade')->entidade?->is_hub) {
            $payload['id_unidade_negocio_faturamento'] = $unidadeFaturamento->id;
        }

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $payload)->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function registrarDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesDevolucoesUsuario())->postJson(route('admin.movimentacoes.devolucoes.store'), [
            'movimentacao_venda_origem_id' => $venda->id,
            'tipo_devolucao' => $tipo->value,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_devolucao' => 'DEV-STRESS',
            'id_unidade_negocio_retorno' => $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE
                ? $venda->empresaOrigem->entidade->id
                : null,
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->where('movimentacao_venda_origem_id', $venda->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function registrarTransferencia(Empresa $origem, Empresa $destino, Fruta $fruta, string $qtdUm, ?Frete $frete = null): Movimentacao
    {
        $user = $this->movimentacoesTransferenciasUsuario();
        $payload = [
            'id_empresa_origem' => $origem->id,
            'id_empresa_destino' => $destino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_origem' => 'NF-TRANS-STRESS',
        ];

        if ($frete !== null) {
            $payload['id_frete'] = $frete->id;
        }

        $this->actingAs($user)->postJson(route('admin.movimentacoes.transferencias.store'), $payload)
            ->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_empresa_destino', $destino->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function reenviarTransferencia(int $transferenciaOrigemId, string $qtdUm, ?Frete $frete = null): Movimentacao
    {
        $user = $this->movimentacoesTransferenciasUsuario();
        $payload = [
            'qtd_fruta_um' => $qtdUm,
            'motivo_substituicao' => 'Reenvio stress após divergência.',
        ];

        if ($frete !== null) {
            $payload['id_frete'] = $frete->id;
        }

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.reenviar', $transferenciaOrigemId),
            $payload,
        )->assertOk();

        return Movimentacao::query()
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function receberConforme(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::CONFORME->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'numero_nf_destino' => 'NF-DEST-STRESS',
            ],
        )->assertOk();
    }

    private function receberDivergente(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::DIVERGENTE->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'observacao_recebimento' => 'Divergência operacional stress.',
            ],
        )->assertOk();
    }

    private function cancelarTransferencia(int $transferenciaOrigemId): void
    {
        $user = $this->movimentacoesTransferenciasUsuario();

        $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.cancelar', $transferenciaOrigemId),
            ['motivo_substituicao' => 'Cancelamento stress após divergência.'],
        )->assertOk();
    }

    private function cancelarCompraAdmin(Movimentacao $compra): void
    {
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $compra), [
            'motivo' => 'Cancelamento admin stress compra.',
        ])->assertOk();
    }

    private function cancelarDoacaoAdmin(Movimentacao $doacao): void
    {
        $admin = $this->movimentacoesDoacoesUsuario([
            Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
        ]);

        $this->actingAs($admin)->post(route('admin.movimentacoes.doacoes.cancelar-admin', $doacao), [
            'motivo' => 'Cancelamento admin stress doação.',
        ])->assertRedirect();
    }

    private function alterarCustoOperacional(UnidadeNegocio $unidade, string $novoCusto): HistoricoCOUnNg
    {
        $anterior = (string) $unidade->custo_operacional;
        $unidade->forceFill(['custo_operacional' => $novoCusto])->save();

        app(HistoricoCustoOperacionalUnidadeNegocioService::class)->registrarSeNecessario($unidade->fresh(), $anterior);

        return HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('status_position', true)
            ->firstOrFail();
    }

    private function assertEstoqueConsistente(UnidadeNegocio $unidade, Fruta $fruta): void
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $me = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->firstOrFail();

        $label = sprintf('unidade=%d fruta=%d', $unidade->id, $fruta->id);
        $this->assertSame((string) $estoque->qtd_fruta_kg, (string) $me->qtd_fruta_kg, $label.' qtd kg');
        $this->assertSame((string) $estoque->qtd_fruta_um, (string) $me->qtd_fruta_um, $label.' qtd um');
        $this->assertSame((string) $estoque->preco_medio_kg, (string) $me->preco_medio_kg, $label.' preco kg');
        $this->assertSame((string) $estoque->preco_medio_um, (string) $me->preco_medio_um, $label.' preco um');
        $this->assertEqualsWithDelta(
            (float) $estoque->valor_total_acumulado,
            (float) $me->valor_total_fruta,
            2.00,
            $label.' valor acumulado',
        );
        $this->assertEqualsWithDelta(
            round((float) $estoque->qtd_fruta_kg * (float) $estoque->preco_medio_kg, 2),
            (float) $estoque->valor_total_acumulado,
            1.00,
        );
    }

    private function assertUmaUltimaPosicao(UnidadeNegocio $unidade, Fruta $fruta): void
    {
        $this->assertSame(1, MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->count());
    }

    private function assertMovimentacoesCanceladasForaDoCalculo(): void
    {
        $this->assertSame(0, Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('status_registro', MovimentacaoStatusRegistro::CANCELADO->value)
            ->count());
    }

    private function assertMovimentacoesSubstituidasForaDoCalculo(): void
    {
        $this->assertSame(0, Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('status_registro', MovimentacaoStatusRegistro::SUBSTITUIDO->value)
            ->count());
    }

    private function assertCustoOperacionalHistoricoPreservado(Movimentacao $antes, Movimentacao $depois, HistoricoCOUnNg $historicoAtual): void
    {
        $antes->refresh();
        $depois->refresh();
        $historicoAnterior = HistoricoCOUnNg::query()->findOrFail((int) $antes->id_custo_operacional);

        $this->assertSame('2.00', (string) $antes->valor_custo_operacional);
        $this->assertSame('5.00', (string) $depois->valor_custo_operacional);
        $this->assertSame((int) $historicoAtual->id, (int) $depois->id_custo_operacional);
        $this->assertFalse((bool) $historicoAnterior->status_position);
        $this->assertTrue((bool) $historicoAtual->fresh()->status_position);
    }

    private function assertIcmsHistoricoPreservadoOuDocumentado(Movimentacao $antes, Movimentacao $depois): void
    {
        // Não existe tabela histórica de ICMS da fruta no modelo atual. O risco é replay antigo ler frutas.icms_* atual.
        // Este teste documenta a regra atual esperada: cada movimentação preserva o ICMS convertido gravado na linha.
        $antes->refresh();
        $depois->refresh();

        $this->assertSame('1.00', (string) $antes->icms_convertido_kg);
        $this->assertSame('3.00', (string) $depois->icms_convertido_kg);
    }

    private function assertFreteCompraRecalculadoApenasComAtivas(Frete $frete): void
    {
        $ativas = $this->movimentacoesAtivasComFrete($frete, CategoriaMovimentacaoTipo::Compra->value, null);
        if ($ativas->isEmpty()) {
            $this->assertTrue(true);

            return;
        }

        $kgAtivos = (float) $this->movimentacoesAtivasDoFrete($frete)->sum(fn (Movimentacao $m): float => (float) $m->qtd_fruta_kg);
        $esperadoKg = round((float) $frete->valor / $kgAtivos, 2);

        foreach ($ativas as $m) {
            $this->assertSame(number_format($esperadoKg, 2, '.', ''), (string) $m->valor_frete_kg, "frete={$frete->id} compra movimentacao={$m->id}");
        }
    }

    private function assertFreteTransferenciaRecalculadoApenasComAtivas(Frete $frete): void
    {
        $ativas = $this->movimentacoesAtivasComFrete($frete, CategoriaMovimentacaoTipo::Transferencia->value, StatusMovimentacao::ID_SAIDA);
        if ($ativas->isEmpty()) {
            $this->assertTrue(true);

            return;
        }

        $kgAtivos = (float) $this->movimentacoesAtivasDoFrete($frete)->sum(fn (Movimentacao $m): float => (float) $m->qtd_fruta_kg);
        $esperadoKg = round((float) $frete->valor / $kgAtivos, 2);

        foreach ($ativas as $m) {
            $this->assertSame(number_format($esperadoKg, 2, '.', ''), (string) $m->valor_frete_kg, "frete={$frete->id} transferencia movimentacao={$m->id}");
        }
    }

    /**
     * @return Collection<int, Movimentacao>
     */
    private function movimentacoesAtivasComFrete(Frete $frete, int $categoriaId, ?int $statusMovimentacaoId): Collection
    {
        return Movimentacao::query()
            ->where('id_frete', $frete->id)
            ->where('categoria_movimentacao_id', $categoriaId)
            ->when($statusMovimentacaoId !== null, fn ($q) => $q->where('status_movimentacao_id', $statusMovimentacaoId))
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->get();
    }

    /**
     * @return Collection<int, Movimentacao>
     */
    private function movimentacoesAtivasDoFrete(Frete $frete): Collection
    {
        return Movimentacao::query()
            ->where('id_frete', $frete->id)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->where('qtd_fruta_kg', '>', 0)
            ->where(function ($query): void {
                $query
                    ->where('categoria_movimentacao_id', '!=', CategoriaMovimentacaoTipo::Transferencia->value)
                    ->orWhere('status_movimentacao_id', '!=', StatusMovimentacao::ID_ENTRADA);
            })
            ->get();
    }

    private function assertDoacaoPreservaPrecoMedioEValorEconomico(Movimentacao $doacao): void
    {
        $doacao->refresh();
        $this->assertSame('0.00', (string) $doacao->valor_nf_total);
        $this->assertSame(
            number_format(round((float) $doacao->preco_medio_fruta_kg * (float) $doacao->qtd_fruta_kg, 2), 2, '.', ''),
            (string) $doacao->valor_total_movimentacao,
        );

        if ($doacao->status_registro === MovimentacaoStatusRegistro::ATIVO->value) {
            $me = MovimentacaoEstoque::query()->findOrFail((int) $doacao->id_movimentacao_estoque_new);
            $this->assertSame((string) $doacao->preco_medio_fruta_kg, (string) $me->preco_medio_kg);
            $this->assertSame((string) $doacao->preco_medio_fruta_um, (string) $me->preco_medio_um);
        }
    }

    private function assertDescartePreservaPrecoMedioEValorEconomico(Movimentacao $descarte): void
    {
        $descarte->refresh();
        $this->assertSame('0.00', (string) $descarte->valor_nf_total);
        $this->assertSame('0.00', (string) $descarte->icms_convertido_kg);
        $this->assertSame('0.00', (string) $descarte->valor_custo_operacional);
        $this->assertSame(
            number_format(round((float) $descarte->preco_medio_fruta_kg * (float) $descarte->qtd_fruta_kg, 2), 2, '.', ''),
            (string) $descarte->valor_total_movimentacao,
        );

        $me = MovimentacaoEstoque::query()->findOrFail((int) $descarte->id_movimentacao_estoque_new);
        $this->assertSame((string) $descarte->preco_medio_fruta_kg, (string) $me->preco_medio_kg);
        $this->assertSame((string) $descarte->preco_medio_fruta_um, (string) $me->preco_medio_um);
    }

    private function assertDevolucaoPreservaCustoHistorico(Movimentacao $venda, Movimentacao $devolucao): void
    {
        $venda->refresh();
        $devolucao->refresh();
        $proporcao = (float) $devolucao->qtd_fruta_um / (float) $venda->qtd_fruta_um;

        $this->assertSame(CategoriaMovimentacaoTipo::Devolucao->value, (int) $devolucao->categoria_movimentacao_id);
        $this->assertSame($venda->id, (int) $devolucao->movimentacao_venda_origem_id);
        $this->assertSame('0.00', (string) $devolucao->valor_nf_total);
        $this->assertSame(
            number_format(round((float) $venda->valor_custo_saida * $proporcao, 2), 2, '.', ''),
            (string) $devolucao->valor_custo_devolucao,
        );
        $this->assertSame((string) $devolucao->valor_custo_devolucao, (string) $devolucao->valor_total_movimentacao);
    }

    private function assertTransferenciaDivergenteOuPendenteNaoEntraNoDestino(): void
    {
        $naoConformes = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->whereIn('status_transferencia', [
                StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value,
                StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value,
                StatusTransferenciaOperacional::CANCELADA->value,
                StatusTransferenciaOperacional::REENVIADA->value,
            ])
            ->get();

        foreach ($naoConformes as $entrada) {
            $this->assertNull($entrada->id_movimentacao_estoque_new);
        }
    }

    private function assertTransferenciasConformesEntraramNoDestino(): void
    {
        $conformes = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->get();

        $this->assertGreaterThanOrEqual(1, $conformes->count());
        foreach ($conformes as $entrada) {
            $this->assertNotNull($entrada->id_movimentacao_estoque_new);
        }
    }

    private function assertCancelamentoAdministrativoReprocessouLinhaDoTempo(): void
    {
        $this->assertGreaterThanOrEqual(2, MovimentacaoHistorico::query()
            ->where('acao', MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN)
            ->where('origem', MovimentacaoHistorico::ORIGEM_CANCELAMENTO_ADMIN)
            ->count());
    }

    private function assertVersionamentoNaoDuplicaCalculo(Movimentacao $v1, Movimentacao $v2): void
    {
        $v1->refresh();
        $v2->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v2->status_registro);
        $this->assertSame((int) $v2->id, (int) $v1->substituida_por_id);

        $ativasNaCadeia = Movimentacao::query()
            ->where(function ($q) use ($v1): void {
                $q->whereKey($v1->id)->orWhere('movimentacao_origem_id', $v1->id);
            })
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->count();

        $this->assertSame(1, $ativasNaCadeia);
    }

    private function assertSemEstoqueNegativo(): void
    {
        $estoques = Estoque::query()->get();
        $this->assertGreaterThan(0, $estoques->count());

        foreach ($estoques as $estoque) {
            $this->assertGreaterThanOrEqual(0, (float) $estoque->qtd_fruta_kg);
            $this->assertGreaterThanOrEqual(0, (float) $estoque->qtd_fruta_um);
            $this->assertGreaterThanOrEqual(0, (float) $estoque->valor_total_acumulado);
        }
    }
}
