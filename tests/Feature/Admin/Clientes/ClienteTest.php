<?php

namespace Tests\Feature\Admin\Clientes;

use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Praca;
use App\Models\UnidadeNegocio;

class ClienteTest extends ClienteTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.clientes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.clientes.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_VISUALIZAR]))
            ->get(route('admin.clientes.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        Cliente::factory()->create([
            'razao_social' => 'CLIENTE DATATABLE',
            'fantasia' => null,
        ]);

        $this->actingAs($this->clientesManager())
            ->get(route('admin.clientes.index'))
            ->assertOk()
            ->assertSee('CLIENTE DATATABLE', false)
            ->assertSee('id="clientes-datatable"', false)
            ->assertSee('data-admin-datatable', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->clientePayload([
            'id_cigam' => '12.3',
            'razao_social' => 'raZao em MiNusCuLa',
            'fantasia' => '  nome   fantasia  ',
            'cnpj_cpf' => '11.222.333/0001-81',
            'desconto_nf' => '10.5',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $payload)
            ->assertStatus(302)
            ->assertSessionHas('success');

        $cliente = Cliente::query()->where('id_cigam', '000123')->firstOrFail();

        $this->assertSame('RAZAO EM MINUSCULA', $cliente->razao_social);
        $this->assertEquals('NOME FANTASIA', $cliente->fantasia);
        $this->assertSame('11222333000181', $cliente->cnpj_cpf);
        $this->assertSame('10.50', (string) $cliente->desconto_nf);
    }

    public function test_cadastro_normaliza_numero_divisao(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_cigam' => '000777',
                'numero_divisao' => '5',
            ]))
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '000777',
            'numero_divisao' => '05',
        ]);
    }

    public function test_cadastro_persiste_dados_de_contato(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_cigam' => '000888',
                'contato_nome' => 'maria gerente',
                'contato_telefone' => '(85) 98877-6655',
                'contato_email' => 'Maria@Loja.COM',
            ]))
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '000888',
            'contato_nome' => 'MARIA GERENTE',
            'contato_telefone' => '85988776655',
            'contato_email' => 'maria@loja.com',
        ]);
    }

    public function test_cadastro_rejeita_telefone_contato_invalido(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'contato_telefone' => '12345',
            ]))
            ->assertSessionHasErrors('contato_telefone');
    }

    public function test_cadastro_aceita_fantasia_null(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'fantasia' => '',
            ]))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '000001',
            'fantasia' => null,
        ]);
    }

    public function test_desconto_negativo_falha_validacao(): void
    {
        $payload = $this->clientePayload([
            'desconto_nf' => '-1',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $payload)
            ->assertSessionHasErrors('desconto_nf');
    }

    public function test_id_cigam_duplicado_falha_validacao(): void
    {
        $existing = Cliente::factory()->create(['id_cigam' => '000099']);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_cigam' => '99',
                'cnpj_cpf' => $existing->cnpj_cpf,
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_cadastro_permite_mesmo_cnpj_cpf_em_clientes_distintos(): void
    {
        Cliente::factory()->create([
            'id_cigam' => '000099',
            'cnpj_cpf' => '11222333000181',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_cigam' => '100',
                'cnpj_cpf' => '11.222.333/0001-81',
            ]))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame(2, Cliente::query()->where('cnpj_cpf', '11222333000181')->count());
    }

    public function test_edicao_atualiza_registro(): void
    {
        $cliente = Cliente::factory()->create([
            'id_cigam' => '000050',
            'razao_social' => 'ANTES',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_EDITAR]))
            ->put(route('admin.clientes.update', $cliente), [
                'id_cigam' => '50',
                'razao_social' => 'DEPOIS',
                'fantasia' => 'Fantasia Depois',
                'cnpj_cpf' => $cliente->cnpj_cpf,
                'id_unidade_negocio' => $cliente->id_unidade_negocio,
                'id_praca' => $cliente->id_praca,
                'grupo_id' => $cliente->grupo_id,
                'desconto_nf' => '1.25',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('DEPOIS', $cliente->fresh()->razao_social);
        $this->assertSame('FANTASIA DEPOIS', $cliente->fresh()->fantasia);
    }

    public function test_busca_encontra_cliente_por_fantasia(): void
    {
        Cliente::factory()->create([
            'razao_social' => 'RAZAO DISTANTE',
            'fantasia' => 'APELIDO COMERCIAL',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_VISUALIZAR]))
            ->get(route('admin.clientes.index', ['search' => 'apelido comercial']))
            ->assertOk()
            ->assertSee('APELIDO COMERCIAL', false);
    }

    public function test_formulario_criacao_lista_apenas_unidades_nao_hub(): void
    {
        $unidadeComum = UnidadeNegocio::factory()->create([
            'nome' => 'LOJA COMUM TESTE',
            'is_hub' => false,
        ]);
        UnidadeNegocio::factory()->create([
            'nome' => 'HUB TESTE',
            'is_hub' => true,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->get(route('admin.clientes.create'))
            ->assertOk()
            ->assertSee('LOJA COMUM TESTE', false)
            ->assertDontSee('HUB TESTE', false)
            ->assertSee('name="id_unidade_negocio"', false)
            ->assertSee('value="'.$unidadeComum->id.'"', false);
    }

    public function test_formulario_criacao_praca_inicia_vazia_ate_selecionar_unidade(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['is_hub' => false]);
        Praca::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'nome' => 'PRACA FILTRADA TESTE',
        ]);

        $html = $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->get(route('admin.clientes.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="cliente-pracas-opcoes"', $html);
        $this->assertStringContainsString('PRACA FILTRADA TESTE', $html);

        preg_match('/<select[^>]*id="id_praca"[^>]*>.*?<\/select>/s', $html, $selectPraca);
        $this->assertCount(1, $selectPraca);
        $this->assertStringContainsString('Selecione a unidade de negócio', $selectPraca[0]);
        $this->assertStringContainsString('disabled', $selectPraca[0]);
        $this->assertDoesNotMatchRegularExpression('/<option value="\d+"/', $selectPraca[0]);
    }

    public function test_cadastro_rejeita_unidade_hub(): void
    {
        $hub = UnidadeNegocio::factory()->create(['is_hub' => true]);
        $praca = Praca::factory()->create(['id_unidade_negocio' => $hub->id]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_unidade_negocio' => $hub->id,
                'id_praca' => $praca->id,
            ]))
            ->assertSessionHasErrors('id_unidade_negocio');
    }
}
