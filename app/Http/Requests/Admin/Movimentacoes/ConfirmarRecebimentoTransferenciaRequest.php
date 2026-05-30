<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarRecebimentoTransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('movimentacoes.transferencias.receber') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
