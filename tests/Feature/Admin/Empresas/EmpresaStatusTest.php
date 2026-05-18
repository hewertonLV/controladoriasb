<?php

namespace Tests\Feature\Admin\Empresas;

use App\Models\Cliente;
use App\Models\Empresa;

class EmpresaStatusTest extends EmpresasTestCase
{
    public function test_excluir_cliente_remove_registro_hub(): void
    {
        $cliente = Cliente::factory()->create();
        $empresa = Empresa::query()
            ->where('entidade_type', Cliente::class)
            ->where('entidade_id', $cliente->id)
            ->firstOrFail();

        $cliente->delete();

        $this->assertSoftDeleted('empresas', ['id' => $empresa->id]);
    }

    public function test_nao_existe_rota_de_exclusao_direta_de_hub(): void
    {
        $user = $this->empresaManager();
        $empresa = Empresa::factory()->create();

        $this->actingAs($user)
            ->delete('/admin/empresas/'.$empresa->id)
            ->assertNotFound();
    }
}
