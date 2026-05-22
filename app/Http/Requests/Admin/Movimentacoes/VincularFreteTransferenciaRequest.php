<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VincularFreteTransferenciaRequest extends FormRequest
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
            'id_frete' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('fretes', 'id')->where('status_situacao', FreteStatusSituacao::ABERTA->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_frete' => 'frete',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('id_frete')) {
            $this->merge(['id_frete' => null]);
        }
    }
}
