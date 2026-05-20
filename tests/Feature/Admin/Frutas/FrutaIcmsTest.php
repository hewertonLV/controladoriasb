<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;
use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcmsAliquota;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Database\Seeders\EstadoSeeder;

class FrutaIcmsTest extends FrutaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
    }

    public function test_usuario_com_permissao_acessa_listagem_icms(): void
    {
        Fruta::factory()->create();

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_ICMS_VISUALIZAR]))
            ->get(route('admin.frutas.icms.index'))
            ->assertOk()
            ->assertSee('ICMS de Frutas');
    }

    public function test_criar_icms_manual_por_fruta_e_estado(): void
    {
        $fruta = Fruta::factory()->create();
        $idEstado = Estado::ID_PERNAMBUCO;

        FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->delete();

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_ICMS_CRIAR]))
            ->post(route('admin.frutas.icms.store'), [
                'fruta_id' => $fruta->id,
                'id_estado' => $idEstado,
                FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '1,50',
                FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '0,50',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '7,00',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '3,00',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '6,00',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '2,50',
            ])
            ->assertRedirect(route('admin.frutas.icms.index'))
            ->assertSessionHas('success');

        $entradaNac = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', FrutaIcmsOperacao::ENTRADA)
            ->where('procedencia', FrutaProcedencia::NACIONAL)
            ->first();

        $this->assertNotNull($entradaNac);
        $this->assertSame(FrutaIcmsTipoValor::VALOR_POR_KG, $entradaNac->tipo_valor);
        $this->assertSame('1.5000', (string) $entradaNac->valor);

        $saidaNacDentro = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', FrutaIcmsOperacao::SAIDA)
            ->where('procedencia', FrutaProcedencia::NACIONAL)
            ->where('escopo_venda', 'DENTRO_ESTADO')
            ->first();

        $this->assertNotNull($saidaNacDentro);
        $this->assertSame(FrutaIcmsTipoValor::PERCENTUAL, $saidaNacDentro->tipo_valor);
        $this->assertSame('7.0000', (string) $saidaNacDentro->valor);
    }

    public function test_editar_icms_atualiza_valores(): void
    {
        $fruta = Fruta::factory()->create();
        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_CEARA, [
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '1.00',
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '5.00',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_ICMS_EDITAR]))
            ->put(route('admin.frutas.icms.update', [$fruta, Estado::ID_CEARA]), [
                FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '9,00',
                FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '0,00',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12,00',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '0,00',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '0,00',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '0,00',
            ])
            ->assertRedirect(route('admin.frutas.icms.index'));

        $entrada = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_CEARA)
            ->where('operacao', FrutaIcmsOperacao::ENTRADA)
            ->where('procedencia', FrutaProcedencia::NACIONAL)
            ->first();

        $this->assertSame('9.0000', (string) $entrada->valor);
    }
}
