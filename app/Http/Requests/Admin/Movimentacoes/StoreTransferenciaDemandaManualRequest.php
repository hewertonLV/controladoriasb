<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferenciaDemandaManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_unidade_negocio_origem' => ['required', 'integer', 'exists:unidades_negocio,id'],
            'id_unidade_negocio_destino' => ['required', 'integer', 'exists:unidades_negocio,id', 'different:id_unidade_negocio_origem'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'linhas.*.qtd_um' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
