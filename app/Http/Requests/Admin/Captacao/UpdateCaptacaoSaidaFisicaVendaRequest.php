<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaptacaoSaidaFisicaVendaRequest extends FormRequest
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
            'id_unidade_negocio_saida_venda' => ['required', 'integer', 'exists:unidades_negocio,id'],
        ];
    }
}
