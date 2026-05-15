<?php

namespace Tests\Feature\Admin\Empresas;

use App\Enums\Permissions;
use App\Models\EmpresaImportacao;
use Illuminate\Support\Str;

class EmpresaImportacaoTest extends EmpresasTestCase
{
    public function test_iniciar_importacao_retorna_410(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_IMPORTAR]);

        $this->actingAs($user)
            ->postJson(route('admin.empresas.importar.iniciar'), [])
            ->assertStatus(410)
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', fn (mixed $m): bool => is_string($m) && str_contains((string) $m, 'descontinuada'));
    }

    public function test_confirmar_retorna_410(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_IMPORTAR_CONFIRMAR]);
        $importacao = EmpresaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'x.xlsx',
            'arquivo_path' => 'empresas/importacoes/x.xlsx',
            'status' => EmpresaImportacao::STATUS_CONCLUIDO,
            'resultado' => [],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.empresas.importar.confirmar', $importacao), [])
            ->assertStatus(410);
    }

    public function test_usuario_nao_acessa_importacao_de_outro_usuario_mas_programador_acessa(): void
    {
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_IMPORTAR]);
        $other = $this->userWithPermissions([Permissions::EMPRESAS_IMPORTAR]);
        $programador = $this->programadorUser();
        $importacao = EmpresaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'arquivo_original' => 'empresas.xlsx',
            'arquivo_path' => 'empresas/importacoes/teste.xlsx',
            'status' => EmpresaImportacao::STATUS_CONCLUIDO,
            'resultado' => [],
        ]);

        $this->actingAs($other)
            ->getJson(route('admin.empresas.importar.status', $importacao))
            ->assertForbidden();

        $this->actingAs($programador)
            ->getJson(route('admin.empresas.importar.status', $importacao))
            ->assertOk()
            ->assertJsonPath('uuid', $importacao->uuid);
    }
}
