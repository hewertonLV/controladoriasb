<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use Illuminate\Foundation\Http\FormRequest;

class CancelarEntradaEstoqueMovimentacaoAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
