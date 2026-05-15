<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\Roles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AutorizaProcessamentoUsuario
{
    protected function autorizarAcessoProcessamento(Request $request, Model $processamento): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $ownerId = $processamento->getAttribute('user_id');
        if ($ownerId !== null && (int) $ownerId === (int) $user->id) {
            return;
        }

        if ($user->hasRole(Roles::PROGRAMADOR->value)) {
            return;
        }

        abort(403, 'Você não tem acesso a este processamento.');
    }
}
