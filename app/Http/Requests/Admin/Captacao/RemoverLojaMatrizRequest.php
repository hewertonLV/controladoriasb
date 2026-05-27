<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class RemoverLojaMatrizRequest extends FormRequest
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
        ];
    }
}
