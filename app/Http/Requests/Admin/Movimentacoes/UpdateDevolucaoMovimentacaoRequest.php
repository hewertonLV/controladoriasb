<?php

namespace App\Http\Requests\Admin\Movimentacoes;

class UpdateDevolucaoMovimentacaoRequest extends StoreDevolucaoMovimentacaoRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo_substituicao' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
