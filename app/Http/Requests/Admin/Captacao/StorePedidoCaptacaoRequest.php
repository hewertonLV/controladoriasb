<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoCaptacaoRequest extends FormRequest
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
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_captacao_rota' => ['nullable', 'integer', 'exists:captacao_rotas,id'],
            'data_entrega' => ['nullable', 'date'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0'],
            'itens.*.preco_venda' => ['nullable', 'numeric', 'min:0'],
            'itens.*.id_unidade_origem_fisica' => ['nullable', 'integer', 'exists:unidades_negocio,id'],
        ];
    }
}
