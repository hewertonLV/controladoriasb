<?php

namespace App\Http\Requests\Admin\Captacao;

class UpdateCaptacaoCarteiraRequest extends StoreCaptacaoCarteiraRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id_clientes' => ['nullable', 'array'],
            'id_clientes.*' => ['integer', 'exists:clientes,id'],
        ]);
    }

    /**
     * @return list<int>
     */
    public function idClientesSelecionados(): array
    {
        return array_values(array_map('intval', (array) $this->input('id_clientes', [])));
    }
}
