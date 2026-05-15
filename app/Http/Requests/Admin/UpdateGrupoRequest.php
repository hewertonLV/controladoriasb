<?php

namespace App\Http\Requests\Admin;

use App\Models\Grupo;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrupoRequest extends FormRequest
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
        /** @var Grupo $grupo */
        $grupo = $this->route('grupo');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grupos', 'nome')->ignore($grupo->id),
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
