<?php

namespace App\Http\Requests\Admin\Movimentacoes\Concerns;

use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Validation\Validator;

trait ValidaAcessoUnidadeNegocio
{
    protected function validarAcessoEmpresaUnidade(Validator $validator, string $campo, string $acao): void
    {
        $empresaId = $this->input($campo);
        if ($empresaId === null || $empresaId === '') {
            return;
        }

        $access = app(UnidadeNegocioAccessService::class);
        $unidadeId = $access->unidadeIdDaEmpresa((int) $empresaId);
        if ($unidadeId === null) {
            return;
        }

        $this->validarAcessoUnidade($validator, $campo, $unidadeId, $acao);
    }

    protected function validarAcessoUnidade(Validator $validator, string $campo, int $unidadeId, string $acao): void
    {
        $user = $this->user();
        if ($user === null) {
            $validator->errors()->add($campo, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);

            return;
        }

        $access = app(UnidadeNegocioAccessService::class);
        $method = 'can'.$acao;
        $permitido = method_exists($access, $method)
            ? $access->{$method}($user, $unidadeId)
            : $access->canAccess($user, $unidadeId);

        if (! $permitido) {
            $validator->errors()->add($campo, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }
}
