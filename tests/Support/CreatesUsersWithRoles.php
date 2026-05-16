<?php

namespace Tests\Support;

use App\Enums\Permissions;
use App\Enums\Roles;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreatesUsersWithRoles
{
    protected function resetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $this->resetPermissionCache();

        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);

        $role = Role::findOrCreate('Teste '.md5($user->email), 'web');

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user->assignRole($role);

        return $user->refresh();
    }

    protected function programadorUser(): User
    {
        $this->resetPermissionCache();

        $user = User::factory()->create([
            'email' => 'programador+'.uniqid().'@example.test',
            'must_change_password' => false,
            'ativo' => true,
        ]);

        $user->assignRole(Role::findOrCreate(Roles::PROGRAMADOR->value, 'web'));

        return $user->refresh();
    }

    protected function userWithoutEmpresaPermissions(): User
    {
        return User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
    }

    protected function empresaManager(): User
    {
        return $this->userWithPermissions([
            Permissions::EMPRESAS_VISUALIZAR,
            Permissions::EMPRESAS_CRIAR,
            Permissions::EMPRESAS_EDITAR,
            Permissions::EMPRESAS_INATIVAR,
            Permissions::EMPRESAS_REATIVAR,
            Permissions::EMPRESAS_IMPORTAR,
            Permissions::EMPRESAS_IMPORTAR_CONFIRMAR,
            Permissions::EMPRESAS_HISTORICO,
            Permissions::EMPRESAS_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Unidades de Negócio (testes).
     */
    protected function unidadesNegocioManager(): User
    {
        return $this->userWithPermissions([
            Permissions::UNIDADES_NEGOCIO_VISUALIZAR,
            Permissions::UNIDADES_NEGOCIO_CRIAR,
            Permissions::UNIDADES_NEGOCIO_EDITAR,
            Permissions::UNIDADES_NEGOCIO_INATIVAR,
            Permissions::UNIDADES_NEGOCIO_ATIVAR,
            Permissions::UNIDADES_NEGOCIO_IMPORTAR,
            Permissions::UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR,
            Permissions::UNIDADES_NEGOCIO_HISTORICO,
            Permissions::UNIDADES_NEGOCIO_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Fornecedores (testes).
     */
    protected function fornecedoresManager(): User
    {
        return $this->userWithPermissions([
            Permissions::FORNECEDORES_VISUALIZAR,
            Permissions::FORNECEDORES_CRIAR,
            Permissions::FORNECEDORES_EDITAR,
            Permissions::FORNECEDORES_IMPORTAR,
            Permissions::FORNECEDORES_IMPORTAR_CONFIRMAR,
            Permissions::FORNECEDORES_HISTORICO,
            Permissions::FORNECEDORES_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Veículos (testes).
     */
    protected function veiculosManager(): User
    {
        return $this->userWithPermissions([
            Permissions::VEICULOS_VISUALIZAR,
            Permissions::VEICULOS_CRIAR,
            Permissions::VEICULOS_EDITAR,
            Permissions::VEICULOS_INATIVAR,
            Permissions::VEICULOS_REATIVAR,
            Permissions::VEICULOS_IMPORTAR,
            Permissions::VEICULOS_IMPORTAR_CONFIRMAR,
            Permissions::VEICULOS_HISTORICO,
            Permissions::VEICULOS_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Fretes (testes).
     */
    protected function fretesManager(): User
    {
        return $this->userWithPermissions([
            Permissions::FRETES_VISUALIZAR,
            Permissions::FRETES_CRIAR,
            Permissions::FRETES_EDITAR,
            Permissions::FRETES_IMPORTAR,
            Permissions::FRETES_IMPORTAR_CONFIRMAR,
            Permissions::FRETES_HISTORICO,
            Permissions::FRETES_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Grupos (testes).
     */
    protected function gruposManager(): User
    {
        return $this->userWithPermissions([
            Permissions::GRUPOS_VISUALIZAR,
            Permissions::GRUPOS_CRIAR,
            Permissions::GRUPOS_EDITAR,
            Permissions::GRUPOS_IMPORTAR,
            Permissions::GRUPOS_IMPORTAR_CONFIRMAR,
            Permissions::GRUPOS_HISTORICO,
            Permissions::GRUPOS_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Clientes (testes).
     */
    protected function clientesManager(): User
    {
        return $this->userWithPermissions([
            Permissions::CLIENTES_VISUALIZAR,
            Permissions::CLIENTES_CRIAR,
            Permissions::CLIENTES_EDITAR,
            Permissions::CLIENTES_IMPORTAR,
            Permissions::CLIENTES_IMPORTAR_CONFIRMAR,
            Permissions::CLIENTES_HISTORICO,
            Permissions::CLIENTES_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Frutas (testes).
     */
    protected function frutasManager(): User
    {
        return $this->userWithPermissions([
            Permissions::FRUTAS_VISUALIZAR,
            Permissions::FRUTAS_CRIAR,
            Permissions::FRUTAS_EDITAR,
            Permissions::FRUTAS_IMPORTAR,
            Permissions::FRUTAS_IMPORTAR_CONFIRMAR,
            Permissions::FRUTAS_HISTORICO,
            Permissions::FRUTAS_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Praças (testes).
     */
    protected function pracasManager(): User
    {
        return $this->userWithPermissions([
            Permissions::PRACAS_VISUALIZAR,
            Permissions::PRACAS_CRIAR,
            Permissions::PRACAS_EDITAR,
            Permissions::PRACAS_IMPORTAR,
            Permissions::PRACAS_IMPORTAR_CONFIRMAR,
            Permissions::PRACAS_HISTORICO,
            Permissions::PRACAS_EXPORTAR_PDF,
        ]);
    }

    /**
     * Usuário com permissões completas do módulo Estoques (testes).
     */
    protected function estoquesManager(): User
    {
        return $this->userWithPermissions([
            Permissions::ESTOQUES_VISUALIZAR,
            Permissions::ESTOQUES_MOVIMENTAR,
            Permissions::ESTOQUES_IMPORTAR,
            Permissions::ESTOQUES_IMPORTAR_CONFIRMAR,
            Permissions::ESTOQUES_EXPORTAR_PDF,
        ]);
    }

    /**
     * Permissões típicas do fluxo web de compra (testes).
     *
     * @param  list<string>  $extra
     */
    protected function movimentacoesComprasUsuario(array $extra = []): User
    {
        return $this->userWithPermissions(array_values(array_unique(array_merge([
            Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
            Permissions::MOVIMENTACOES_COMPRAS_EDITAR,
        ], $extra))));
    }

    /**
     * Permissões típicas do fluxo web de transferência (testes).
     *
     * @param  list<string>  $extra
     */
    protected function movimentacoesTransferenciasUsuario(array $extra = []): User
    {
        return $this->userWithPermissions(array_values(array_unique(array_merge([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_RECEBER,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_REENVIAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR,
        ], $extra))));
    }

    /**
     * Permissões típicas do fluxo web de doação (testes).
     *
     * @param  list<string>  $extra
     */
    protected function movimentacoesDoacoesUsuario(array $extra = []): User
    {
        return $this->userWithPermissions(array_values(array_unique(array_merge([
            Permissions::MOVIMENTACOES_DOACOES_VISUALIZAR,
            Permissions::MOVIMENTACOES_DOACOES_CRIAR,
            Permissions::MOVIMENTACOES_DOACOES_EDITAR,
        ], $extra))));
    }
}
