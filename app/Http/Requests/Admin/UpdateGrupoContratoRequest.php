<?php

namespace App\Http\Requests\Admin;

use App\Models\GrupoContrato;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrupoContratoRequest extends FormRequest
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
        /** @var GrupoContrato $grupoContrato */
        $grupoContrato = $this->route('grupoContrato');

        return [
            'nome' => ['required', 'string', 'max:255', Rule::unique('grupos_contrato', 'nome')->ignore($grupoContrato->id)],
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
