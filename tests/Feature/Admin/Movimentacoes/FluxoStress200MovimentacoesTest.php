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
use App\Services\UnidadesNegocio\HistoricoCustoOperacionalUnidadeNegocioService;
use Database\Seeders\CategoriaDescarteSeeder;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class FluxoStress200MovimentacoesTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private int $eventosExecutados = 0;

    /** @var array<string, int> */
    private array $contadores = [
        'compras' => 0,
        'transferencias' => 0,
        'vendas' => 0,
        'devolucoes' => 0,
        'doacoes' => 0,
        'descartes' => 0,
        'cancelamentos' => 0,
        'correcoes' => 0,
    ];

    /** @var list<Movimentacao> */
    private array $compras = [];

    /** @var list<Movimentacao> */
    private array $transferencias = [];

    /** @var list<Movimentacao> */
    private array $vendas = [];

    /** @var list<Movimentacao> */
    private array $devolucoes = [];

    /** @var list<Movimentacao> */
    private array $doacoes = [];

    /** @var list<Movimentacao> */
    private array $descartes = [];

    /** @var array<string, mixed> */
    private array $cenario = [];

    private ?HistoricoCOUnNg $historicoCustoAlterado = null;

    private ?Movimentacao $compraAntesIcms = null;

    private ?Movimentacao $compraDepoisIcms = null;

    public function test_stress_200_movimentacoes_com_todas_as_categorias_e_invariantes(): void
    {
        $this->seedBase();
        $this->cenario = $this->cenarioStress();

        // Bloco 1: 45 compras, com fretes compartilhados e mudança de ICMS/custo no meio.
        for ($i = 1; $i <= 45; $i++) {
            if ($i === 16) {
                $this->historicoCustoAlterado = $this->alterarCustoOperacional(
                    $this->cenario['unidades'][2],
                    '4.00',
                );
            }

            if ($i === 31) {
                foreach ($this->cenario['frutas'] as $fruta) {
                    $fruta->forceFill(['icms_na_compra' => 2, 'icms_ex_compra' => 0])->save();
                }
            }

            $compra = $this->evento('compras', fn () => $this->registrarCompra(
                fornecedor: $this->cenario['fornecedores'][$i % 3],
                destino: $this->cenario['empresas_unidades'][$i % 4],
                fruta: $this->cenario['frutas'][$i % 5],
                frete: $this->cenario['fretes_compra'][$i % 2],
                qtdUm: '6',
                valorNfTotal: number_format(300 + ($i * 7), 2, ',', '.'),
            ));

            if ($i === 8) {
                $this->compraAntesIcms = $compra;
            }
            if ($i === 37) {
                $this->compraDepoisIcms = $compra;
            }

            if (in_array($i, [5, 15, 25], true)) {
                $this->evento('correcoes', fn () => $this->atualizarCompra($compra, number_format(700 + $i, 2, ',', '.')));
            }
        }

        // Bloco 2: 35 eventos de transferência, incluindo conformes, divergentes, reenvio e cancelamento.
        for ($i = 1; $i <= 10; $i++) {
            $saida = $this->evento('transferencias', fn () => $this->registrarTransferencia(
                origem: $this->cenario['empresas_unidades'][$i % 4],
                destino: $this->cenario['empresas_unidades'][($i + 1) % 4],
                fruta: $this->cenario['frutas'][$i % 5],
                qtdUm: '0.50',
                frete: $this->cenario['frete_transferencia'],
            ));
            $this->transferencias[] = $saida;
            $this->evento('transferencias', fn () => $this->receberConforme((int) $saida->transferencia_origem_id, '0.50'));
        }

        for ($i = 1; $i <= 3; $i++) {
            $saida = $this->evento('transferencias', fn () => $this->registrarTransferencia(
                origem: $this->cenario['empresas_unidades'][($i + 1) % 4],
                destino: $this->cenario['empresas_unidades'][($i + 2) % 4],
                fruta: $this->cenario['frutas'][($i + 2) % 5],
                qtdUm: '0.60',
                frete: null,
            ));
            $this->transferencias[] = $saida;
            $this->evento('transferencias', fn () => $this->receberDivergente((int) $saida->transferencia_origem_id, '0.30'));
            $reenviada = $this->evento('transferencias', fn () => $this->reenviarTransferencia((int) $saida->transferencia_origem_id, '0.30'));
            $this->transferencias[] = $reenviada;
            $this->evento('transferencias', fn () => $this->receberConforme((int) $reenviada->transferencia_origem_id, '0.30'));
        }

        $pendente = $this->evento('transferencias', fn () => $this->registrarTransferencia(
            origem: $this->cenario['empresas_unidades'][0],
            destino: $this->cenario['empresas_unidades'][2],
            fruta: $this->cenario['frutas'][0],
            qtdUm: '0.40',
            frete: null,
        ));
        $this->transferencias[] = $pendente;
        $this->evento('transferencias', fn () => $this->cancelarTransferencia((int) $pendente->transferencia_origem_id));
        $this->transferencias[] = $this->evento('transferencias', fn () => $this->registrarTransferencia(
            origem: $this->cenario['empresas_unidades'][3],
            destino: $this->cenario['empresas_unidades'][1],
            fruta: $this->cenario['frutas'][4],
            qtdUm: '0.20',
            frete: $this->cenario['frete_transferencia'],
        ));

        // Bloco 3: 30 vendas, com frete compartilhado e estoque negativo permitido na venda do HUB.
        for ($i = 1; $i <= 30; $i++) {
            $venda = $this->evento('vendas', fn () => $this->registrarVenda(
                origem: $i === 30 ? $this->cenario['empresa_hub'] : $this->cenario['empresas_unidades'][$i % 4],
                cliente: $this->cenario['clientes'][$i % 3],
                unidadeFaturamento: $this->cenario['unidades'][($i % 3) + 1],
                fruta: $this->cenario['frutas'][$i % 5],
                qtdUm: $i === 30 ? '50.00' : '0.50',
                valorNfTotal: $i === 30 ? '5.000,00' : number_format(120 + $i, 2, ',', '.'),
                frete: $this->cenario['fretes_venda'][$i % 2],
            ));
            $this->vendas[] = $venda;
        }

        // Bloco 4: 25 devoluções parciais, com e sem retorno físico.
        for ($i = 0; $i < 25; $i++) {
            $tipo = $i % 3 === 0 ? TipoDevolucao::SEM_RETORNO_ESTOQUE : TipoDevolucao::COM_RETORNO_ESTOQUE;
            $this->devolucoes[] = $this->evento('devolucoes', fn () => $this->registrarDevolucao(
                venda: $this->vendas[$i],
                tipo: $tipo,
                qtdUm: '0.10',
                unidadeRetorno: $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE
                    ? $this->cenario['unidades'][($i + 1) % 4]
                    : null,
            ));
        }

        // Bloco 5: 20 doações.
        for ($i = 1; $i <= 20; $i++) {
            $fruta = $this->cenario['frutas'][$i % 5];
            $this->doacoes[] = $this->evento('doacoes', fn () => $this->registrarDoacao(
                origem: $this->empresaComSaldoParaSaida($fruta, '0.10'),
                fruta: $fruta,
                qtdUm: '0.10',
                cliente: $this->cenario['clientes'][$i % 3],
            ));
        }

        // Bloco 6: 20 descartes.
        for ($i = 1; $i <= 20; $i++) {
            $fruta = $this->cenario['frutas'][($i + 1) % 5];
            $this->descartes[] = $this->evento('descartes', fn () => $this->registrarDescarte(
                origem: $this->empresaComSaldoParaSaida($fruta, '0.10'),
                fruta: $fruta,
                qtdUm: '0.10',
            ));
        }

        // Bloco 7: 15 cancelamentos administrativos.
        foreach (array_slice($this->compras, -3) as $compra) {
            $this->evento('cancelamentos', fn () => $this->cancelarCompraAdmin($compra));
        }
        foreach (array_slice($this->vendas, -3) as $venda) {
            $this->evento('cancelamentos', fn () => $this->cancelarVendaAdmin($venda));
        }
        foreach (array_slice(array_values(array_filter(
            $this->devolucoes,
            fn (Movimentacao $d): bool => $d->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value,
        )), 0, 3) as $devolucao) {
            $this->evento('cancelamentos', fn () => $this->cancelarDevolucaoAdmin($devolucao));
        }
        foreach (array_slice($this->doacoes, 0, 3) as $doacao) {
            $this->evento('cancelamentos', fn () => $this->cancelarDoacaoAdmin($doacao));
        }
        foreach (array_slice($this->descartes, 0, 3) as $descarte) {
            $this->evento('cancelamentos', fn () => $this->cancelarDescarteAdmin($descarte));
        }

        // Bloco 8: 7 correções/versionamentos finais; as 3 correções de compra ocorreram no ponto em que ainda eram última posição.
        foreach (array_slice($this->vendas, 20, 3) as $idx => $venda) {
            $this->evento('correcoes', fn () => $this->atualizarVenda($venda, '0.60', number_format(180 + ($idx * 10), 2, ',', '.')));
        }
        foreach (array_slice($this->devolucoes, 10, 4) as $devolucao) {
            $this->evento('correcoes', fn () => $this->atualizarDevolucao($devolucao, '0.05'));
        }

        $this->assertSame(200, $this->eventosExecutados);
        $this->assertSame([
            'compras' => 45,
            'transferencias' => 35,
            'vendas' => 30,
            'devolucoes' => 25,
            'doacoes' => 20,
            'descartes' => 20,
            'cancelamentos' => 15,
            'correcoes' => 10,
        ], $this->contadores);

        $this->assertInvariantesGerais();
        $this->assertVendaComEstoqueNegativoPermitida();
        $this->assertComprasPreservamIcmsHistorico();
        $this->assertCustoOperacionalHistoricoPreservado();
        $this->assertAuditoriaMinima();
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
    private function cenarioStress(): array
    {
        $hub = $this->criarUnidade('UNIDADE HUB CEARA', Estado::ID_CEARA, '0.00', true);
        $ce = $this->criarUnidade('UNIDADE COMERCIAL CEARA', Estado::ID_CEARA, '1.00', false);
        $pe = $this->criarUnidade('UNIDADE COMERCIAL PERNAMBUCO', Estado::ID_PERNAMBUCO, '2.00', false);
        $al = $this->criarUnidade('UNIDADE COMERCIAL ALAGOAS', Estado::ID_ALAGOAS, '3.00', false);

        $fornecedores = [
            Fornecedor::factory()->create(['razao_social' => 'FORNECEDOR CEARA STRESS', 'id_estado' => Estado::ID_CEARA])->registroCorporativo()->firstOrFail(),
            Fornecedor::factory()->create(['razao_social' => 'FORNECEDOR PERNAMBUCO STRESS', 'id_estado' => Estado::ID_PERNAMBUCO])->registroCorporativo()->firstOrFail(),
            Fornecedor::factory()->create(['razao_social' => 'FORNECEDOR ALAGOAS STRESS', 'id_estado' => Estado::ID_ALAGOAS])->registroCorporativo()->firstOrFail(),
        ];

        $clientes = [
            Cliente::factory()->create(['razao_social' => 'CLIENTE 1 STRESS', 'id_unidade_negocio' => $ce->id])->registroCorporativo()->firstOrFail(),
            Cliente::factory()->create(['razao_social' => 'CLIENTE 2 STRESS', 'id_unidade_negocio' => $pe->id])->registroCorporativo()->firstOrFail(),
            Cliente::factory()->create(['razao_social' => 'CLIENTE 3 STRESS', 'id_unidade_negocio' => $al->id])->registroCorporativo()->firstOrFail(),
        ];

        $frutas = [
            $this->criarFruta('BANANA STRESS 200', 10),
            $this->criarFruta('MAMAO STRESS 200', 8),
            $this->criarFruta('ABACAXI STRESS 200', 12),
            $this->criarFruta('LARANJA STRESS 200', 15),
            $this->criarFruta('LIMAO STRESS 200', 5),
        ];

        return [
            'unidades' => [$hub, $ce, $pe, $al],
            'empresa_hub' => $hub->registroCorporativo()->firstOrFail(),
            'empresas_unidades' => [
                $hub->registroCorporativo()->firstOrFail(),
                $ce->registroCorporativo()->firstOrFail(),
                $pe->registroCorporativo()->firstOrFail(),
                $al->registroCorporativo()->firstOrFail(),
            ],
            'fornecedores' => $fornecedores,
            'clientes' => $clientes,
            'frutas' => $frutas,
            'fretes_compra' => [
                $this->criarFrete('FRETE COMPRA 1 STRESS 200', '300,00'),
                $this->criarFrete('FRETE COMPRA 2 STRESS 200', '200,00'),
            ],
            'frete_transferencia' => $this->criarFrete('FRETE TRANSFERENCIA 1 STRESS 200', '150,00'),
            'fretes_venda' => [
                $this->criarFrete('FRETE VENDA 1 STRESS 200', '100,00'),
                $this->criarFrete('FRETE VENDA 2 STRESS 200', '120,00'),
            ],
        ];
    }

    private function criarUnidade(string $nome, int $estadoId, string $custoOperacional, bool $isHub): UnidadeNegocio
    {
        $unidade = UnidadeNegocio::factory()->create([
            'nome' => $nome,
            'razao_social' => $nome,
            'possui_estoque' => true,
            'id_estado' => $estadoId,
            'custo_operacional' => $custoOperacional,
            'is_hub' => $isHub,
        ]);

        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => $custoOperacional,
            'status_position' => true,
        ]);

        return $unidade;
    }

    private function criarFruta(string $nome, int $kgPorUm): Fruta
    {
        return Fruta::factory()->create([
            'nome' => $nome,
            'kg_por_unidade_medicao' => $kgPorUm,
            'icms_na_compra' => 1,
            'icms_ex_compra' => 0,
            'um_icms' => FrutaUmIcms::KG->value,
        ]);
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

    private function empresaComSaldoParaSaida(Fruta $fruta, string $qtdUm): Empresa
    {
        $qtd = (float) $qtdUm;

        foreach ($this->cenario['unidades'] as $unidade) {
            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $fruta->id)
                ->first();

            if ($estoque !== null && (float) $estoque->qtd_fruta_um >= $qtd) {
                return $unidade->registroCorporativo()->firstOrFail();
            }
        }

        $this->fail('Nenhuma unidade com saldo suficiente para saída da fruta '.$fruta->id);
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function evento(string $tipo, callable $callback): mixed
    {
        $this->eventosExecutados++;
        $this->contadores[$tipo]++;

        $resultado = $callback();

        if ($this->eventosExecutados % 25 === 0) {
            $this->assertInvariantesGerais();
        }

        return $resultado;
    }

    private function registrarCompra(Empresa $fornecedor, Empresa $destino, Fruta $fruta, Frete $frete, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $fornecedor->id,
            'id_empresa_destino' => $destino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $frete->id,
        ])->assertCreated();

        $compra = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_empresa_destino', $destino->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->compras[] = $compra;

        return $compra;
    }

    private function atualizarCompra(Movimentacao $compra, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->putJson(route('admin.movimentacoes.compras.update', $compra), [
            'valor_nf_total' => $valorNfTotal,
            'motivo_substituicao' => 'Correção stress 200 compra.',
        ])->assertOk();

        return Movimentacao::query()->findOrFail((int) $compra->fresh()->substituida_por_id);
    }

    private function registrarTransferencia(Empresa $origem, Empresa $destino, Fruta $fruta, string $qtdUm, ?Frete $frete): Movimentacao
    {
        $payload = [
            'id_empresa_origem' => $origem->id,
            'id_empresa_destino' => $destino->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_origem' => 'NF-TRANS-STRESS-200',
        ];

        if ($frete !== null) {
            $payload['id_frete'] = $frete->id;
        }

        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(route('admin.movimentacoes.transferencias.store'), $payload)
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

    private function receberConforme(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::CONFORME->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'numero_nf_destino' => 'NF-DEST-STRESS-200',
            ],
        )->assertOk();
    }

    private function receberDivergente(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(
            route('admin.movimentacoes.transferencias.recebimento.store', $transferenciaOrigemId),
            [
                'status_recebimento' => StatusRecebimentoTransferencia::DIVERGENTE->value,
                'qtd_recebida_um' => $qtdRecebidaUm,
                'observacao_recebimento' => 'Divergência stress 200.',
            ],
        )->assertOk();
    }

    private function reenviarTransferencia(int $transferenciaOrigemId, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(
            route('admin.movimentacoes.transferencias.reenviar', $transferenciaOrigemId),
            [
                'qtd_fruta_um' => $qtdUm,
                'motivo_substituicao' => 'Reenvio stress 200.',
            ],
        )->assertOk();

        return Movimentacao::query()
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function cancelarTransferencia(int $transferenciaOrigemId): void
    {
        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(
            route('admin.movimentacoes.transferencias.cancelar', $transferenciaOrigemId),
            ['motivo_substituicao' => 'Cancelamento stress 200 transferência.'],
        )->assertOk();
    }

    private function registrarVenda(Empresa $origem, Empresa $cliente, UnidadeNegocio $unidadeFaturamento, Fruta $fruta, string $qtdUm, string $valorNfTotal, ?Frete $frete): Movimentacao
    {
        $payload = [
            'numero_nf' => 'NF-VENDA-STRESS-200',
            'id_empresa_origem' => $origem->id,
            'id_empresa_destino' => $cliente->id,
            'itens' => [
                ['id_fruta' => $fruta->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ];

        if ($origem->loadMissing('entidade')->entidade?->is_hub) {
            $payload['id_unidade_negocio_faturamento'] = $unidadeFaturamento->id;
        }

        if ($frete !== null) {
            $payload['id_frete'] = $frete->id;
        }

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $payload)
            ->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function atualizarVenda(Movimentacao $venda, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->putJson(route('admin.movimentacoes.vendas.update', $venda), [
            'numero_nf' => $venda->vendaNota?->numero_nf ?? 'NF-VENDA-STRESS-200',
            'id_empresa_origem' => $venda->id_empresa_origem,
            'id_empresa_destino' => $venda->id_empresa_destino,
            'id_fruta' => $venda->id_fruta,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $venda->id_frete,
            'motivo_substituicao' => 'Correção stress 200 venda.',
        ])->assertOk();

        return Movimentacao::query()->findOrFail((int) $venda->fresh()->substituida_por_id);
    }

    private function registrarDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm, ?UnidadeNegocio $unidadeRetorno): Movimentacao
    {
        $payload = [
            'movimentacao_venda_origem_id' => $venda->id,
            'tipo_devolucao' => $tipo->value,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_devolucao' => 'DEV-STRESS-200',
        ];

        if ($tipo === TipoDevolucao::COM_RETORNO_ESTOQUE) {
            $payload['id_unidade_negocio_retorno'] = $unidadeRetorno?->id ?? $venda->empresaOrigem->entidade->id;
        }

        $this->actingAs($this->movimentacoesDevolucoesUsuario())->postJson(route('admin.movimentacoes.devolucoes.store'), $payload)
            ->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->where('movimentacao_venda_origem_id', $venda->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function atualizarDevolucao(Movimentacao $devolucao, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesDevolucoesUsuario())->putJson(route('admin.movimentacoes.devolucoes.update', $devolucao), [
            'movimentacao_venda_origem_id' => $devolucao->movimentacao_venda_origem_id,
            'tipo_devolucao' => $devolucao->tipo_devolucao,
            'id_unidade_negocio_retorno' => $devolucao->id_unidade_negocio_retorno,
            'qtd_fruta_um' => $qtdUm,
            'numero_nf_devolucao' => $devolucao->numero_nf_devolucao,
            'motivo_substituicao' => 'Correção stress 200 devolução.',
        ])->assertOk();

        return Movimentacao::query()->findOrFail((int) $devolucao->fresh()->substituida_por_id);
    }

    private function registrarDoacao(Empresa $origem, Fruta $fruta, string $qtdUm, ?Empresa $cliente = null): Movimentacao
    {
        $payload = [
            'id_empresa_origem' => $origem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'motivo_doacao' => 'Doação stress 200.',
        ];

        if ($cliente !== null) {
            $payload['id_empresa_destino'] = $cliente->id;
        }

        $this->actingAs($this->movimentacoesDoacoesUsuario())->post(route('admin.movimentacoes.doacoes.store'), $payload)
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
            'motivo_descarte' => 'Descarte stress 200.',
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('id_empresa_origem', $origem->id)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function cancelarCompraAdmin(Movimentacao $compra): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.compras.cancelar-admin', $compra), ['motivo' => 'Cancelamento stress 200 compra.'])
            ->assertOk();
    }

    private function cancelarVendaAdmin(Movimentacao $venda): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), ['motivo' => 'Cancelamento stress 200 venda.'])
            ->assertOk();
    }

    private function cancelarDevolucaoAdmin(Movimentacao $devolucao): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.devolucoes.cancelar-admin', $devolucao), ['motivo' => 'Cancelamento stress 200 devolução.'])
            ->assertOk();
    }

    private function cancelarDoacaoAdmin(Movimentacao $doacao): void
    {
        $this->actingAs($this->movimentacoesDoacoesUsuario([Permissions::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN]))
            ->post(route('admin.movimentacoes.doacoes.cancelar-admin', $doacao), ['motivo' => 'Cancelamento stress 200 doação.'])
            ->assertRedirect();
    }

    private function cancelarDescarteAdmin(Movimentacao $descarte): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.descartes.cancelar-admin', $descarte), ['motivo' => 'Cancelamento stress 200 descarte.'])
            ->assertOk();
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

    private function assertInvariantesGerais(): void
    {
        $this->assertEstoquesConsistentes();
        $this->assertUmaUltimaPosicaoPorEstoque();
        $this->assertCanceladasForaDoCalculo();
        $this->assertSubstituidasForaDoCalculo();
        $this->assertVersoesAtivasNaoDuplicamCalculo();
        $this->assertSemPrecoMedioNegativo();
        $this->assertFretesConsistentes();
        $this->assertVendasComResultadoValido();
        $this->assertDevolucoesValidas();
        $this->assertSaidasPreservamPrecoMedio();
        $this->assertTransferenciasPendentesDivergentesNaoEntramNoDestino();
        $this->assertTransferenciasConformesEntraramNoDestino();
    }

    private function assertEstoquesConsistentes(): void
    {
        foreach (Estoque::query()->get() as $estoque) {
            $me = MovimentacaoEstoque::query()
                ->where('id_estoque', $estoque->id)
                ->where('status_ultima_posicao', true)
                ->first();

            $this->assertNotNull($me, 'Estoque sem última posição: '.$estoque->id);
            $this->assertSame((string) $estoque->qtd_fruta_kg, (string) $me->qtd_fruta_kg);
            $this->assertSame((string) $estoque->qtd_fruta_um, (string) $me->qtd_fruta_um);
            $this->assertSame((string) $estoque->preco_medio_kg, (string) $me->preco_medio_kg);
            $this->assertSame((string) $estoque->preco_medio_um, (string) $me->preco_medio_um);
            $this->assertEqualsWithDelta((float) $estoque->valor_total_acumulado, (float) $me->valor_total_fruta, 1.00);
        }
    }

    private function assertUmaUltimaPosicaoPorEstoque(): void
    {
        foreach (Estoque::query()->get() as $estoque) {
            $this->assertSame(1, MovimentacaoEstoque::query()
                ->where('id_estoque', $estoque->id)
                ->where('status_ultima_posicao', true)
                ->count(), 'Estoque com mais de uma última posição: '.$estoque->id);
        }
    }

    private function assertCanceladasForaDoCalculo(): void
    {
        $this->assertSame(0, Movimentacao::query()->vigentesParaCalculo()->where('status_registro', MovimentacaoStatusRegistro::CANCELADO->value)->count());
    }

    private function assertSubstituidasForaDoCalculo(): void
    {
        $this->assertSame(0, Movimentacao::query()->vigentesParaCalculo()->where('status_registro', MovimentacaoStatusRegistro::SUBSTITUIDO->value)->count());
    }

    private function assertVersoesAtivasNaoDuplicamCalculo(): void
    {
        $raizes = Movimentacao::query()
            ->whereNotNull('movimentacao_origem_id')
            ->orWhereNotNull('substituida_por_id')
            ->get()
            ->map(fn (Movimentacao $m): int => $m->idCadeiaRaiz())
            ->unique();

        foreach ($raizes as $raiz) {
            $ativas = Movimentacao::query()
                ->where(function ($q) use ($raiz): void {
                    $q->whereKey($raiz)->orWhere('movimentacao_origem_id', $raiz);
                })
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->count();
            $this->assertLessThanOrEqual(1, $ativas, 'Mais de uma versão ativa na cadeia '.$raiz);
        }
    }

    private function assertSemPrecoMedioNegativo(): void
    {
        foreach (Estoque::query()->get() as $estoque) {
            $this->assertGreaterThanOrEqual(0, (float) $estoque->preco_medio_kg);
            $this->assertGreaterThanOrEqual(0, (float) $estoque->preco_medio_um);
        }
    }

    private function assertFretesConsistentes(): void
    {
        foreach (Frete::query()->get() as $frete) {
            foreach ([CategoriaMovimentacaoTipo::Compra, CategoriaMovimentacaoTipo::Transferencia, CategoriaMovimentacaoTipo::Venda] as $categoria) {
                $query = Movimentacao::query()
                    ->vigentesParaCalculo()
                    ->where('id_frete', $frete->id)
                    ->where('categoria_movimentacao_id', $categoria->value);

                if ($categoria === CategoriaMovimentacaoTipo::Transferencia || $categoria === CategoriaMovimentacaoTipo::Venda) {
                    $query->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA);
                }

                $movs = $query->get();
                if ($movs->isEmpty()) {
                    continue;
                }

                $kgAtivo = (float) $movs->sum(fn (Movimentacao $m): float => (float) $m->qtd_fruta_kg);
                if ($kgAtivo <= 0) {
                    continue;
                }

                $esperado = number_format(round((float) $frete->valor / $kgAtivo, 2), 2, '.', '');
                foreach ($movs as $m) {
                    $this->assertSame($esperado, (string) $m->valor_frete_kg);
                }
            }
        }
    }

    private function assertVendasComResultadoValido(): void
    {
        $vendas = Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->get();
        foreach ($vendas as $venda) {
            $this->assertGreaterThanOrEqual(0, (float) $venda->valor_custo_saida);
            $this->assertSame(
                number_format(round((float) $venda->valor_nf_total - (float) $venda->valor_custo_saida - (float) $venda->valor_frete_rateio, 2), 2, '.', ''),
                (string) $venda->resultado_movimentacao,
            );
        }
    }

    private function assertDevolucoesValidas(): void
    {
        $devolucoes = Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)->get();
        foreach ($devolucoes as $devolucao) {
            $this->assertSame((string) $devolucao->valor_custo_devolucao, (string) $devolucao->valor_total_movimentacao);
            $this->assertSame('0.00', (string) $devolucao->valor_nf_total);

            if ($devolucao->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value && $devolucao->status_registro === MovimentacaoStatusRegistro::ATIVO->value) {
                $this->assertNotNull($devolucao->id_unidade_negocio_retorno);
                $this->assertNotNull($devolucao->id_movimentacao_estoque_new);
            }

            if ($devolucao->tipo_devolucao === TipoDevolucao::SEM_RETORNO_ESTOQUE->value) {
                $this->assertNull($devolucao->id_movimentacao_estoque_new);
                $this->assertLessThanOrEqual(0, (float) $devolucao->resultado_devolucao);
            }
        }

        foreach (Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->get() as $venda) {
            $this->assertGreaterThanOrEqual(-0.0001, $venda->saldoDevolvivelUm());
            $this->assertGreaterThanOrEqual(-0.0001, $venda->saldoDevolvivelKg());
        }
    }

    private function assertSaidasPreservamPrecoMedio(): void
    {
        $saidas = Movimentacao::query()
            ->whereIn('categoria_movimentacao_id', [
                CategoriaMovimentacaoTipo::Venda->value,
                CategoriaMovimentacaoTipo::Doacao->value,
                CategoriaMovimentacaoTipo::Descarte->value,
            ])
            ->whereNotNull('id_movimentacao_estoque_new')
            ->get();

        foreach ($saidas as $saida) {
            $me = MovimentacaoEstoque::query()->findOrFail((int) $saida->id_movimentacao_estoque_new);
            $this->assertSame((string) $saida->preco_medio_fruta_kg, (string) $me->preco_medio_kg);
            $this->assertSame((string) $saida->preco_medio_fruta_um, (string) $me->preco_medio_um);

            if (in_array((int) $saida->categoria_movimentacao_id, [CategoriaMovimentacaoTipo::Doacao->value, CategoriaMovimentacaoTipo::Descarte->value], true)) {
                $this->assertGreaterThanOrEqual(0, (float) $saida->valor_total_movimentacao);
            }
        }
    }

    private function assertTransferenciasPendentesDivergentesNaoEntramNoDestino(): void
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

        foreach ($conformes as $entrada) {
            $this->assertNotNull($entrada->id_movimentacao_estoque_new);
        }
    }

    private function assertVendaComEstoqueNegativoPermitida(): void
    {
        $this->assertTrue(Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('saldo_estoque_fruta_kg', '<', 0)
            ->exists());
    }

    private function assertComprasPreservamIcmsHistorico(): void
    {
        $this->assertNotNull($this->compraAntesIcms);
        $this->assertNotNull($this->compraDepoisIcms);
        $this->assertNotSame((string) $this->compraAntesIcms->fresh()->icms_convertido_kg, (string) $this->compraDepoisIcms->fresh()->icms_convertido_kg);
    }

    private function assertCustoOperacionalHistoricoPreservado(): void
    {
        $this->assertNotNull($this->historicoCustoAlterado);
        $this->assertTrue((bool) $this->historicoCustoAlterado->fresh()->status_position);
        $this->assertTrue(Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('valor_custo_operacional', '4.00')
            ->exists());
    }

    private function assertAuditoriaMinima(): void
    {
        $this->assertGreaterThanOrEqual(25, MovimentacaoHistorico::query()->count());
        $this->assertGreaterThanOrEqual(10, MovimentacaoHistorico::query()
            ->where('acao', MovimentacaoHistorico::ACAO_CANCELAMENTO_ADMIN)
            ->count());
    }
}
