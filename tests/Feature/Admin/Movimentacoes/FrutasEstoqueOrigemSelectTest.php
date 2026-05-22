<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\Movimentacoes\FrutasComEstoqueOrigem;
use Database\Seeders\CategoriaDescarteSeeder;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class FrutasEstoqueOrigemSelectTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            CategoriaDescarteSeeder::class,
        ]);
    }

    public function test_movimentacoes_exceto_compra_exibem_frutas_anotadas_pelo_estoque_da_origem(): void
    {
        $this->seedBase();

        [$origemComBanana, $banana, $mamao, $laranjaSemEstoque] = $this->cenarioFrutasPorOrigem();

        Cliente::factory()->create(['razao_social' => 'CLIENTE SELECT FRUTA ORIGEM']);
        Frete::factory()->create([
            'nome' => 'FRETE SELECT FRUTA ORIGEM',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
        ]);

        $rotas = [
            [route('admin.movimentacoes.transferencias.create'), Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR],
            [route('admin.movimentacoes.doacoes.create'), Permissions::MOVIMENTACOES_DOACOES_CRIAR],
            [route('admin.movimentacoes.descartes.create'), Permissions::MOVIMENTACOES_DESCARTES_CRIAR],
            [route('admin.movimentacoes.vendas.create'), Permissions::MOVIMENTACOES_VENDAS_CRIAR],
        ];

        foreach ($rotas as [$rota, $permissao]) {
            $assertVenda = str_contains($rota, 'vendas');
            $assertTransferencia = str_contains($rota, 'transferencias');
            $html = $this->actingAs($this->userWithPermissions([$permissao]))
                ->get($rota)
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString($banana->nome, (string) $html);
            $this->assertStringContainsString($mamao->nome, (string) $html);
            $this->assertStringNotContainsString($laranjaSemEstoque->nome, (string) $html);
            $this->assertStringContainsString(
                sprintf('value="%d" data-estoque-origens="%d"', $banana->id, $origemComBanana->registroCorporativo()->firstOrFail()->id),
                (string) $html,
            );

            if ($assertVenda) {
                $this->assertStringContainsString('origem física', strtolower((string) $html));
                $this->assertStringContainsString('não do cliente', strtolower((string) $html));
                $this->assertStringContainsString('data-venda-origem', (string) $html);
            }

            if ($assertTransferencia) {
                $this->assertStringContainsString('data-transferencia-origem', (string) $html);
                $this->assertStringContainsString('data-transferencia-fruta-aviso', (string) $html);
                $this->assertStringContainsString('change.select2.transferenciaOrigem', (string) $html);
            }
        }
    }

    public function test_listar_vincula_empresa_quando_unidade_com_estoque_nao_tem_registro_corporativo(): void
    {
        $this->seedBase();

        $unidade = UnidadeNegocio::factory()->create([
            'nome' => 'UNIDADE SEM EMPRESA ESTOQUE',
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidade->id)
            ->forceDelete();

        $fruta = Fruta::factory()->create([
            'nome' => 'FRUTA EMPRESA LAZY',
            'kg_por_unidade_medicao' => 10,
        ]);
        Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => '2.00',
            'qtd_fruta_kg' => '20.00',
            'preco_medio_um' => '10.00',
            'preco_medio_kg' => '1.00',
            'valor_total_acumulado' => '20.00',
        ]);

        $frutas = FrutasComEstoqueOrigem::listar();

        $this->assertCount(1, $frutas);
        $empresa = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidade->id)
            ->first();
        $this->assertNotNull($empresa);
        $this->assertContains($empresa->id, $frutas->first()->estoque_origem_empresa_ids);
    }

    public function test_compra_continua_listando_frutas_mesmo_sem_estoque_de_origem(): void
    {
        $this->seedBase();

        [, , , $laranjaSemEstoque] = $this->cenarioFrutasPorOrigem();
        Fornecedor::factory()->create(['razao_social' => 'FORNECEDOR COMPRA FRUTA SEM ESTOQUE']);
        Frete::factory()->create([
            'nome' => 'FRETE COMPRA FRUTA SEM ESTOQUE',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
        ]);

        $html = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]))
            ->get(route('admin.movimentacoes.compras.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($laranjaSemEstoque->nome, (string) $html);
    }

    /**
     * @return array{0: UnidadeNegocio, 1: Fruta, 2: Fruta, 3: Fruta}
     */
    private function cenarioFrutasPorOrigem(): array
    {
        $origemComBanana = UnidadeNegocio::factory()->create([
            'nome' => 'ORIGEM COM BANANA SELECT',
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $origemComMamao = UnidadeNegocio::factory()->create([
            'nome' => 'ORIGEM COM MAMAO SELECT',
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
        ]);
        UnidadeNegocio::factory()->create([
            'nome' => 'ORIGEM SEM FRUTA SELECT',
            'possui_estoque' => true,
            'id_estado' => Estado::ID_ALAGOAS,
        ]);

        $banana = Fruta::factory()->create(['nome' => 'BANANA SELECT ORIGEM', 'kg_por_unidade_medicao' => 10]);
        $mamao = Fruta::factory()->create(['nome' => 'MAMAO SELECT ORIGEM', 'kg_por_unidade_medicao' => 8]);
        $laranjaSemEstoque = Fruta::factory()->create(['nome' => 'LARANJA SEM ESTOQUE SELECT', 'kg_por_unidade_medicao' => 6]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $origemComBanana->id,
            'id_fruta' => $banana->id,
            'qtd_fruta_um' => '3.00',
            'qtd_fruta_kg' => '30.00',
            'preco_medio_um' => '20.00',
            'preco_medio_kg' => '2.00',
            'valor_total_acumulado' => '60.00',
        ]);
        Estoque::factory()->create([
            'id_unidade_negocio' => $origemComMamao->id,
            'id_fruta' => $mamao->id,
            'qtd_fruta_um' => '4.00',
            'qtd_fruta_kg' => '32.00',
            'preco_medio_um' => '30.00',
            'preco_medio_kg' => '3.75',
            'valor_total_acumulado' => '120.00',
        ]);

        return [$origemComBanana, $banana, $mamao, $laranjaSemEstoque];
    }
}
