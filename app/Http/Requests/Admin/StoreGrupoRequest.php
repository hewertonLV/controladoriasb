<?php

namespace App\Http\Requests\Admin;

use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrupoRequest extends FormRequest
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
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grupos', 'nome'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'nome',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome', '')),
        ]);
    }
}
