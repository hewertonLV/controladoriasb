<?php

namespace App\Http\Requests\Admin;

use App\Models\Fruta;
use Illuminate\Validation\Rule;

class UpdateFrutaRequest extends StoreFrutaRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Fruta $fruta */
        $fruta = $this->route('fruta');

        $regras = parent::rules();
        $regras['id_cigam'] = [
            'required',
            'string',
            'regex:/^\d{6}$/',
            Rule::unique('frutas', 'id_cigam')->ignore($fruta->id),
        ];

        return $regras;
    }
}
