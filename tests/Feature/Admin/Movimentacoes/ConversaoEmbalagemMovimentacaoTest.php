<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fruta;
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

class ConversaoEmbalagemMovimentacaoTest extends TestCase
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

    public function test_usuario_converte_embalagem_em_fruta_resultante_e_registra_perda(): void
    {
        $this->seedBase();
        [$unidade, $origem, $destino] = $this->cenarioComEstoque();
        $empresa = $unidade->registroCorporativo()->firstOrFail();

        $response = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_CONVERSOES_EMBALAGEM_CRIAR, Permissions::MOVIMENTACOES_CONVERSOES_EMBALAGEM_VISUALIZAR]))
            ->post(route('admin.movimentacoes.conversoes-embalagem.store'), [
                'id_empresa_origem' => $empresa->id,
                'id_fruta_origem' => $origem->id,
                'qtd_fruta_um' => '10',
                'id_fruta_destino' => $destino->id,
                'qtd_resultante_um' => '9',
                'observacao' => 'Abrir pacote para venda a granel.',
            ]);

        $response->assertRedirect();

        $saida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::ConversaoEmbalagem->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->firstOrFail();
        $entrada = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::ConversaoEmbalagem->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->firstOrFail();

        $this->assertSame((int) $entrada->id, (int) $saida->pareada_movimentacao_id);
        $this->assertSame((int) $saida->id, (int) $entrada->conversao_origem_id);
        $this->assertSame('10.00', (string) $saida->qtd_fruta_um);
        $this->assertSame('200.00', (string) $saida->qtd_fruta_kg);
        $this->assertSame('9.00', (string) $saida->qtd_resultante_um);
        $this->assertSame('180.00', (string) $saida->qtd_resultante_kg);
        $this->assertSame('1.00', (string) $saida->qtd_perda_conversao_um);
        $this->assertSame('20.00', (string) $saida->qtd_perda_conversao_kg);
        $this->assertSame('50.00', (string) $saida->valor_perda_conversao);
        $this->assertSame('500.00', (string) $saida->valor_total_movimentacao);
        $this->assertSame('450.00', (string) $entrada->valor_total_movimentacao);
        $this->assertSame('9.00', (string) $entrada->qtd_fruta_um);
        $this->assertSame('180.00', (string) $entrada->qtd_fruta_kg);

        $estoqueOrigem = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $origem->id)->firstOrFail();
        $estoqueDestino = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $destino->id)->firstOrFail();

        $this->assertSame('0.00', (string) $estoqueOrigem->qtd_fruta_kg);
        $this->assertSame('0.00', (string) $estoqueOrigem->qtd_fruta_um);
        $this->assertSame('0.00', (string) $estoqueOrigem->valor_total_acumulado);
        $this->assertSame('280.00', (string) $estoqueDestino->qtd_fruta_kg);
        $this->assertSame('14.00', (string) $estoqueDestino->qtd_fruta_um);
        $this->assertSame('1250.00', (string) $estoqueDestino->valor_total_acumulado);
        $this->assertSame('4.46', (string) $estoqueDestino->preco_medio_kg);
        $this->assertSame('89.20', (string) $estoqueDestino->preco_medio_um);

        $this->assertDatabaseHas('movimentacao_historicos', [
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_CONVERSAO_EMBALAGEM,
            'origem' => MovimentacaoHistorico::ORIGEM_CONVERSAO_EMBALAGEM,
        ]);
    }

    public function test_nao_permite_resultante_pesar_mais_que_original(): void
    {
        $this->seedBase();
        [$unidade, $origem, $destino] = $this->cenarioComEstoque();
        $empresa = $unidade->registroCorporativo()->firstOrFail();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_CONVERSOES_EMBALAGEM_CRIAR]))
            ->post(route('admin.movimentacoes.conversoes-embalagem.store'), [
                'id_empresa_origem' => $empresa->id,
                'id_fruta_origem' => $origem->id,
                'qtd_fruta_um' => '1',
                'id_fruta_destino' => $destino->id,
                'qtd_resultante_um' => '30',
            ])
            ->assertSessionHasErrors(['qtd_resultante_um']);

        $this->assertSame(0, Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::ConversaoEmbalagem->value)->count());
    }

    public function test_ignora_estoque_destino_removido_logicamente_e_cria_novo_estoque(): void
    {
        $this->seedBase();
        [$unidade, $origem, $destino] = $this->cenarioComEstoque();
        $empresa = $unidade->registroCorporativo()->firstOrFail();

        $estoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $destino->id)
            ->firstOrFail();
        $estoqueDestino->delete();

        $this->actingAs($this->userWithPermissions([
            Permissions::MOVIMENTACOES_CONVERSOES_EMBALAGEM_CRIAR,
            Permissions::MOVIMENTACOES_CONVERSOES_EMBALAGEM_VISUALIZAR,
        ]))->post(route('admin.movimentacoes.conversoes-embalagem.store'), [
            'id_empresa_origem' => $empresa->id,
            'id_fruta_origem' => $origem->id,
            'qtd_fruta_um' => '1',
            'id_fruta_destino' => $destino->id,
            'qtd_resultante_um' => '1',
        ])->assertRedirect();

        $novoEstoqueDestino = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $destino->id)
            ->firstOrFail();

        $this->assertSame(2, Estoque::query()->count());
        $this->assertSame(3, Estoque::withTrashed()->count());
        $this->assertFalse(Estoque::query()->whereKey($estoqueDestino->id)->exists());
        $this->assertNotNull($estoqueDestino->fresh()->deleted_at);
        $this->assertNotSame((int) $estoqueDestino->id, (int) $novoEstoqueDestino->id);
        $this->assertSame('20.00', (string) $novoEstoqueDestino->qtd_fruta_kg);
        $this->assertSame('1.00', (string) $novoEstoqueDestino->qtd_fruta_um);
        $this->assertSame('50.00', (string) $novoEstoqueDestino->valor_total_acumulado);
        $this->assertFalse(MovimentacaoEstoque::query()
            ->where('id_estoque', $estoqueDestino->id)
            ->where('status_ultima_posicao', true)
            ->exists());
    }

    /**
     * @return array{UnidadeNegocio, Fruta, Fruta}
     */
    private function cenarioComEstoque(): array
    {
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);

        $origem = Fruta::factory()->create([
            'nome' => 'LARANJA PACOTE TESTE',
            'kg_por_unidade_medicao' => '20.00',
        ]);
        $destino = Fruta::factory()->create([
            'nome' => 'LARANJA GRANEL TESTE',
            'kg_por_unidade_medicao' => '20.00',
        ]);

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $origem->id,
            'qtd_fruta_kg' => '200.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '2.50',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $origem->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '200.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '2.50',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        $estoqueDestino = Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $destino->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '5.00',
            'preco_medio_kg' => '8.00',
            'preco_medio_um' => '160.00',
            'valor_total_acumulado' => '800.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueDestino->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $destino->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '5.00',
            'preco_medio_kg' => '8.00',
            'preco_medio_um' => '160.00',
            'valor_total_fruta' => '800.00',
            'status_ultima_posicao' => true,
        ]);

        return [$unidade, $origem, $destino];
    }
}
