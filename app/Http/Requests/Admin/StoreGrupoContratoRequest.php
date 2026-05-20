<?php

namespace App\Http\Requests\Admin;

use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrupoContratoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255', Rule::unique('grupos_contrato', 'nome')],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome', '')),
            'descricao' => trim((string) $this->input('descricao', '')) ?: null,
            'ativo' => $this->boolean('ativo'),
        ]);
    }
}
