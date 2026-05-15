<?php

namespace Tests\Feature\Admin\Fretes;

use App\Enums\FreteStatusSituacao;
use App\Enums\Permissions;
use App\Models\Frete;
use App\Models\Veiculo;

class FreteTest extends FreteTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.fretes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.fretes.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRETES_VISUALIZAR]))
            ->get(route('admin.fretes.index'))
            ->assertOk();
    }

    public function test_listagem_ajax_retorna_partial_da_tabela(): void
    {
        Frete::factory()->create(['nome' => 'FRETE AJAX']);

        $this->actingAs($this->fretesManager())
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html',
            ])
            ->get(route('admin.fretes.index'))
            ->assertOk()
            ->assertSee('FRETE AJAX', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $veiculo = Veiculo::factory()->create(['id_sbs' => 999]);

        $payload = $this->fretePayload([
            'nome' => 'frete em minúsculas',
            'valor' => '10,5',
            'id_veiculo' => $veiculo->id,
            'descricao' => '  texto  ',
            'status_situacao' => 'aberta',
            'valor_fruta_kg' => '1,25',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRETES_CRIAR]))
            ->post(route('admin.fretes.store'), $payload)
            ->assertRedirect(route('admin.fretes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fretes', [
            'nome' => 'FRETE EM MINÚSCULAS',
            'valor' => '10.50',
            'id_veiculo' => $veiculo->id,
            'descricao' => 'texto',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
            'valor_fruta_kg' => '1.25',
        ]);
    }

    public function test_nome_duplicado_falha_na_validacao(): void
    {
        Frete::factory()->create(['nome' => 'FRETE DUPLICADO']);

        $this->actingAs($this->userWithPermissions([Permissions::FRETES_CRIAR]))
            ->post(route('admin.fretes.store'), $this->fretePayload([
                'nome' => 'frete duplicado',
            ]))
            ->assertSessionHasErrors('nome');
    }

    public function test_edicao_atualiza_registro(): void
    {
        $veiculo = Veiculo::factory()->create();
        $frete = Frete::factory()->create([
            'nome' => 'ANTES',
            'id_veiculo' => $veiculo->id,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRETES_EDITAR]))
            ->put(route('admin.fretes.update', $frete), [
                'nome' => 'Depois',
                'valor' => '200',
                'id_veiculo' => $veiculo->id,
                'descricao' => null,
                'status_situacao' => FreteStatusSituacao::ENCERRADA->value,
                'valor_fruta_kg' => '0',
            ])
            ->assertRedirect(route('admin.fretes.index'))
            ->assertSessionHas('success');

        $frete->refresh();
        $this->assertSame('DEPOIS', $frete->nome);
        $this->assertSame(FreteStatusSituacao::ENCERRADA->value, $frete->status_situacao);
    }
}
