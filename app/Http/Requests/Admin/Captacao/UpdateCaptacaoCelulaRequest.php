<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaptacaoCelulaRequest extends FormRequest
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
            'id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'quantidade' => ['nullable', 'numeric', 'min:0'],
            'incremento' => ['nullable', 'numeric'],
            'preco_venda' => ['nullable', 'numeric', 'min:0'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
