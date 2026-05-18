<?php

namespace Tests\Feature\Admin\GruposContrato;

use App\Enums\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class GrupoContratoTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function grupoContratoPayload(array $overrides = []): array
    {
        return array_replace([
            'nome' => 'Grupo Contrato Teste',
            'descricao' => 'Contrato com desconto mensal',
            'ativo' => '1',
        ], $overrides);
    }

    /**
     * @param  list<string>  $extra
     */
    protected function grupoContratoUsuario(array $extra = [])
    {
        return $this->userWithPermissions(array_values(array_unique(array_merge([
            Permissions::GRUPOS_CONTRATO_VISUALIZAR,
            Permissions::GRUPOS_CONTRATO_CRIAR,
            Permissions::GRUPOS_CONTRATO_EDITAR,
            Permissions::GRUPOS_CONTRATO_MEMBROS,
            Permissions::GRUPOS_CONTRATO_DESCONTOS,
            Permissions::GRUPOS_CONTRATO_HISTORICO,
        ], $extra))));
    }
}
