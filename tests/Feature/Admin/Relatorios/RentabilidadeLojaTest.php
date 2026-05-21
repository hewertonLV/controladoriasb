<?php

namespace Tests\Feature\Admin\Relatorios;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\Permissions;
use App\Enums\TipoDevolucao;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Relatorios\RentabilidadeLojaService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class RentabilidadeLojaTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_sem_permissao_retorna_403(): void
    {
        $this->seedBase();

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.relatorios.rentabilidade-loja.index'))
            ->assertForbidden();
    }

    public function test_relatorio_consolida_venda_devolucao_e_resultado_liquido_por_cliente(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $devolucao = $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '2');

        $venda->refresh();
        $devolucao->refresh();

        $dataInicio = $venda->data_movimentacao->toDateString();
        $dataFim = $devolucao->data_movimentacao->toDateString();

        $user = $this->userWithPermissions([
            Permissions::RELATORIOS_RENTABILIDADE_LOJA_VISUALIZAR,
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_VENDAS_CRIAR,
            Permissions::MOVIMENTACOES_DEVOLUCOES_CRIAR,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.relatorios.rentabilidade-loja.index', [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'agrupamento' => 'cliente',
            ]));

        $response->assertOk();
        $response->assertSee('Rentabilidade por loja', false);

        /** @var array<string, mixed> $dados */
        $dados = $response->viewData('dados');

        $this->assertCount(1, $dados['linhas']);
        $linha = $dados['linhas'][0];

        $this->assertSame((float) $venda->resultado_movimentacao, $linha['venda_resultado']);
        $this->assertSame((float) $devolucao->resultado_devolucao, $linha['dev_resultado']);
        $this->assertSame(
            round((float) $venda->resultado_movimentacao + (float) $devolucao->resultado_devolucao, 2),
            $linha['resultado_liquido'],
        );
        $this->assertSame((float) $venda->valor_custo_saida, $linha['venda_custo_saida']);
        $this->assertSame(40.0, $linha['venda_qtd_kg']);
        $this->assertSame(20.0, $linha['dev_qtd_kg']);

        $service = app(RentabilidadeLojaService::class);
        $detalhe = $service->gerar($user, [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'agrupamento' => 'detalhe',
        ]);

        $this->assertCount(1, $detalhe['linhas']);
        $this->assertNotSame('—', $detalhe['linhas'][0]['fruta_nome']);
        $this->assertNotSame('—', $detalhe['linhas'][0]['unidade_origem_nome']);
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
    private function cenarioBase(): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create();
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'cliente' => $cliente,
            'fruta' => Fruta::factory()->create(['kg_por_unidade_medicao' => 10]),
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
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->orderByDesc('id')->firstOrFail();
    }

    private function registrarDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), [
                'movimentacao_venda_origem_id' => $venda->id,
                'tipo_devolucao' => $tipo->value,
                'qtd_fruta_um' => $qtdUm,
                'numero_nf_devolucao' => 'DEV-001',
                'motivo_devolucao' => 'Devolução de teste.',
                'id_unidade_negocio_retorno' => $venda->empresaOrigem->entidade->id,
            ])
            ->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)->orderByDesc('id')->firstOrFail();
    }
}
