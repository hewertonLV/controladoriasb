<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\Permissions;
use App\Enums\Roles;
use Illuminate\Foundation\Http\FormRequest;

class CancelarDevolucaoMovimentacaoAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasRole(Roles::ADMINISTRADOR->value)
                || $user->can(Permissions::MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
