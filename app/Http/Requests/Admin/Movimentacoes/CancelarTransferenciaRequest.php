<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use Illuminate\Foundation\Http\FormRequest;

class CancelarTransferenciaRequest extends FormRequest
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
            'motivo_substituicao' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'motivo_substituicao' => 'motivo do cancelamento',
        ];
    }
}
