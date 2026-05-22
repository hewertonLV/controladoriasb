<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUnidadeMedicao;
use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcmsAliquota;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;

class FrutaTest extends FrutaTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.frutas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.frutas.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_VISUALIZAR]))
            ->get(route('admin.frutas.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        Fruta::factory()->create(['nome' => 'MANGA DATATABLE']);

        $this->actingAs($this->frutasManager())
            ->get(route('admin.frutas.index'))
            ->assertOk()
            ->assertSee('MANGA DATATABLE', false)
            ->assertSee('id="frutas-datatable"', false)
            ->assertSee('data-admin-datatable', false)
            ->assertSee('dataTables_filter', false)
            ->assertSee('assets/js/admin-datatable.js', false);
    }

    public function test_admin_datatable_js_forca_draw_apos_busca_na_toolbar(): void
    {
        $js = file_get_contents(public_path('assets/js/admin-datatable.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString('applyToolbarSearch', $js);
        $this->assertStringContainsString('redrawFilteredRows', $js);
        $this->assertStringContainsString('normalizeOrder', $js);
        $this->assertStringContainsString("search: ''", $js);
        $this->assertStringContainsString('table.draw(false)', $js);
        $this->assertStringContainsString("off('keyup.DT search.DT input.DT')", $js);
        $this->assertStringContainsString('search.dt.adminDtPdfSync', $js);
        $this->assertStringNotContainsString(
            "debugLog('event:search.dt', { term: table.search() });\n                    table.draw();",
            $js,
        );
        $this->assertStringContainsString('purgeAttributeFilterHandlers', $js);
        $this->assertStringContainsString('isDataTable', $js);
        $this->assertStringNotContainsString('buildDrawCallbackEnforceVisibleRows', $js);
    }

    public function test_listagem_filtra_por_pesquisa_na_url(): void
    {
        Fruta::factory()->create(['nome' => 'MANGA FILTRO']);
        Fruta::factory()->create(['nome' => 'BANANA OUTRA']);

        $this->actingAs($this->frutasManager())
            ->get(route('admin.frutas.index', ['search' => 'MANGA']))
            ->assertOk()
            ->assertSee('MANGA FILTRO', false)
            ->assertDontSee('BANANA OUTRA', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->frutaPayload([
            'id_cigam' => '1.2-3',
            'nome' => 'maçã fuji',
            'unidade_medicao' => 'caixa',
            'kg_por_unidade_medicao' => '12.5',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $payload)
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('frutas', [
            'id_cigam' => '000123',
            'nome' => 'MAÇÃ FUJI',
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '12.50',
        ]);
    }

    public function test_id_cigam_duplicado_falha_na_validacao(): void
    {
        Fruta::factory()->create(['id_cigam' => '000099']);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'id_cigam' => '99',
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_unidade_medicao_invalida_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'unidade_medicao' => 'INVALIDA',
            ]))
            ->assertSessionHasErrors('unidade_medicao');
    }

    public function test_kg_negativo_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'kg_por_unidade_medicao' => -1,
            ]))
            ->assertSessionHasErrors('kg_por_unidade_medicao');
    }

    public function test_cadastro_bdj_bandeja(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'id_cigam' => '88',
                'nome' => 'Morango Bandeja',
                'unidade_medicao' => FrutaUnidadeMedicao::BDJ->value,
                'kg_por_unidade_medicao' => '1.25',
            ]))
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('frutas', [
            'id_cigam' => '000088',
            'nome' => 'MORANGO BANDEJA',
            'unidade_medicao' => FrutaUnidadeMedicao::BDJ->value,
            'kg_por_unidade_medicao' => '1.25',
        ]);
    }

    public function test_cadastro_kg_persiste_kg_por_unidade_com_tres_casas(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'id_cigam' => '99',
                'nome' => 'Banana Kg',
                'unidade_medicao' => FrutaUnidadeMedicao::KG->value,
                'kg_por_unidade_medicao' => '1',
            ]))
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('frutas', [
            'id_cigam' => '000099',
            'nome' => 'BANANA KG',
            'unidade_medicao' => FrutaUnidadeMedicao::KG->value,
            'kg_por_unidade_medicao' => '1.000',
        ]);
    }

    public function test_cadastro_pct_persiste_kg_com_tres_casas_decimais(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'id_cigam' => '77',
                'nome' => 'Uva Pacote',
                'unidade_medicao' => FrutaUnidadeMedicao::PCT->value,
                'kg_por_unidade_medicao' => '0.35',
            ]))
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('frutas', [
            'id_cigam' => '000077',
            'nome' => 'UVA PACOTE',
            'unidade_medicao' => FrutaUnidadeMedicao::PCT->value,
            'kg_por_unidade_medicao' => '0.350',
        ]);
    }

    public function test_edicao_atualiza_registro(): void
    {
        $fruta = Fruta::factory()->create([
            'id_cigam' => '000050',
            'nome' => 'ANTES',
            'unidade_medicao' => FrutaUnidadeMedicao::PACOTE->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_EDITAR]))
            ->put(route('admin.frutas.update', $fruta), $this->frutaPayload([
                'id_cigam' => '50',
                'nome' => 'Depois',
                'unidade_medicao' => FrutaUnidadeMedicao::SACO->value,
                'kg_por_unidade_medicao' => '20',
                'icms' => [
                    Estado::ID_CEARA => [
                        FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '2.00',
                        FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '1.00',
                        FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '18.00',
                        FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '5.00',
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $fruta->refresh();
        $this->assertSame('000050', $fruta->id_cigam);
        $this->assertSame('DEPOIS', $fruta->nome);
        $this->assertSame(FrutaUnidadeMedicao::SACO->value, $fruta->unidade_medicao);
        $this->assertSame('20.00', $fruta->kg_por_unidade_medicao);
        $icmsEntrada = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_CEARA)
            ->where('operacao', FrutaIcmsOperacao::ENTRADA)
            ->where('procedencia', FrutaProcedencia::NACIONAL)
            ->first();
        $this->assertNotNull($icmsEntrada);
        $this->assertSame('2.0000', (string) $icmsEntrada->valor);
    }
}
