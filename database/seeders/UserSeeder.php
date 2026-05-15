<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder idempotente do usuário base do sistema.
 *
 * Regras:
 * - Usa `firstOrCreate` por `email` (chave única confiável).
 * - Se o usuário já existe: NÃO sobrescreve nome, senha, login nem demais campos.
 * - Senha é definida apenas na criação.
 * - Garante o vínculo com a role Programador (idempotente: assignRole não duplica).
 * - Não atribui permissões diretamente — acesso total via Gate::before.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'hewerton@sitiobarreiras.com.br'],
            [
                'name' => 'Hewerton Vieira',
                'login' => 'hewerton',
                'password' => Hash::make('casa1234'),
                'email_verified_at' => now(),
                'must_change_password' => false,
                'ativo' => true,
            ],
        );

        if (! $user->hasRole(Roles::PROGRAMADOR->value)) {
            $user->assignRole(Roles::PROGRAMADOR->value);
        }
    }
}
