<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class SyncClienteFrutaVinculoRequest extends FormRequest
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
            'id_frutas' => ['nullable', 'array'],
            'id_frutas.*' => ['integer', 'exists:frutas,id'],
        ];
    }
}
