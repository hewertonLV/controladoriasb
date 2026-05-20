<?php

namespace Tests\Feature\Admin\GruposContrato;

use App\Models\Cliente;
use App\Models\GrupoContrato;
use App\Models\GrupoContratoCliente;
use App\Models\GrupoContratoDesconto;
use Illuminate\Support\Facades\Schema;

class GrupoContratoTest extends GrupoContratoTestCase
{
    public function test_clientes_nao_possuem_mais_coluna_desconto_contrato(): void
    {
        $this->assertFalse(Schema::hasColumn('clientes', 'desconto_contrato'));
    }

    public function test_usuario_com_permissao_acessa_listagem(): void
    {
        GrupoContrato::factory()->create(['nome' => 'CONTRATO BANANA']);

        $this->actingAs($this->grupoContratoUsuario(['grupos-contrato.visualizar']))
            ->get(route('admin.grupos-contrato.index'))
            ->assertOk()
            ->assertSee('CONTRATO BANANA', false)
            ->assertSee('id="grupos-contrato-datatable"', false)
            ->assertSee('data-admin-datatable', false);
    }

    public function test_cadastro_de_grupo_contrato_normaliza_nome_e_audita(): void
    {
        $this->actingAs($this->grupoContratoUsuario(['grupos-contrato.criar']))
            ->post(route('admin.grupos-contrato.store'), $this->grupoContratoPayload([
                'nome' => '  contrato maçã  ',
            ]))
            ->assertRedirect(route('admin.grupos-contrato.index'))
            ->assertSessionHas('success');

        $grupo = GrupoContrato::query()->where('nome', 'CONTRATO MAÇÃ')->firstOrFail();

        $this->assertDatabaseHas('grupo_contrato_historicos', [
            'grupo_contrato_id' => $grupo->id,
            'acao' => 'CRIACAO',
        ]);
    }

    public function test_vinculo_de_cliente_por_competencia_permite_consulta_historica(): void
    {
        $grupo = GrupoContrato::factory()->create();
        $cliente = Cliente::factory()->create();

        GrupoContratoCliente::factory()->create([
            'grupo_contrato_id' => $grupo->id,
            'cliente_id' => $cliente->id,
            'competencia_inicio' => '2026-05',
            'competencia_fim' => '2026-07',
        ]);

        $this->assertTrue($grupo->clientesNaCompetencia('2026-06')->whereKey($cliente->id)->exists());
        $this->assertFalse($grupo->clientesNaCompetencia('2026-08')->whereKey($cliente->id)->exists());
    }

    public function test_nao_permite_sobrepor_participacao_do_cliente_no_mesmo_grupo(): void
    {
        $grupo = GrupoContrato::factory()->create();
        $cliente = Cliente::factory()->create();

        GrupoContratoCliente::factory()->create([
            'grupo_contrato_id' => $grupo->id,
            'cliente_id' => $cliente->id,
            'competencia_inicio' => '2026-05',
            'competencia_fim' => '2026-07',
        ]);

        $this->actingAs($this->grupoContratoUsuario(['grupos-contrato.membros']))
            ->post(route('admin.grupos-contrato.membros.store', $grupo), [
                'cliente_id' => $cliente->id,
                'competencia_inicio' => '2026-06',
                'competencia_fim' => '2026-08',
            ])
            ->assertSessionHasErrors('competencia_inicio');
    }

    public function test_lanca_desconto_mensal_informativo_com_historico(): void
    {
        $grupo = GrupoContrato::factory()->create();

        $this->actingAs($this->grupoContratoUsuario(['grupos-contrato.descontos']))
            ->post(route('admin.grupos-contrato.descontos.store', $grupo), [
                'competencia' => '2026-05',
                'valor' => '1500.25',
                'valor_teto' => '2000.00',
                'observacao' => 'Perdas do mês',
            ])
            ->assertRedirect(route('admin.grupos-contrato.show', $grupo));

        $desconto = GrupoContratoDesconto::query()->where('grupo_contrato_id', $grupo->id)->firstOrFail();

        $this->assertSame('2026-05', $desconto->competencia);
        $this->assertSame('1500.25', (string) $desconto->valor);
        $this->assertDatabaseHas('grupo_contrato_desconto_historicos', [
            'grupo_contrato_desconto_id' => $desconto->id,
            'acao' => 'CRIACAO',
        ]);
    }
}
