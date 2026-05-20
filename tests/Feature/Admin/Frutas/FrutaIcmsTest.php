<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcms;
use App\Services\Frutas\FrutaIcmsSyncService;
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

        FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->delete();

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_ICMS_CRIAR]))
            ->post(route('admin.frutas.icms.store'), [
                'fruta_id' => $fruta->id,
                'id_estado' => $idEstado,
                'entrada_nacional' => '1,50',
                'entrada_um_nacional' => FrutaUmIcms::KG->value,
                'entrada_externo' => '0,50',
                'entrada_um_externo' => FrutaUmIcms::KG->value,
                'saida_importada' => '3,00',
                'saida_um_importada' => FrutaUmIcms::KG->value,
                'saida_nacional' => '7,00',
                'saida_um_nacional' => FrutaUmIcms::KG->value,
            ])
            ->assertRedirect(route('admin.frutas.icms.index'))
            ->assertSessionHas('success');

        $entrada = FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', 'ENTRADA')
            ->first();

        $this->assertNotNull($entrada);
        $this->assertSame('1.50', (string) $entrada->icms_nacional);

        $saida = FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', 'SAIDA')
            ->first();

        $this->assertNotNull($saida);
        $this->assertSame('7.00', (string) $saida->icms_venda_nacional);
    }

    public function test_editar_icms_atualiza_valores(): void
    {
        $fruta = Fruta::factory()->create();
        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_CEARA, [
            'entrada_nacional' => '1.00',
            'saida_nacional' => '5.00',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_ICMS_EDITAR]))
            ->put(route('admin.frutas.icms.update', [$fruta, Estado::ID_CEARA]), [
                'entrada_nacional' => '9,00',
                'entrada_um_nacional' => FrutaUmIcms::UM->value,
                'entrada_externo' => '0,00',
                'entrada_um_externo' => FrutaUmIcms::KG->value,
                'saida_importada' => '0,00',
                'saida_um_importada' => FrutaUmIcms::KG->value,
                'saida_nacional' => '12,00',
                'saida_um_nacional' => FrutaUmIcms::KG->value,
            ])
            ->assertRedirect(route('admin.frutas.icms.index'));

        $entrada = FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_CEARA)
            ->where('operacao', 'ENTRADA')
            ->first();

        $this->assertSame('9.00', (string) $entrada->icms_nacional);
        $this->assertSame(FrutaUmIcms::UM->value, $entrada->um_icms_nacional);
    }
}
