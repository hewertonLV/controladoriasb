<?php

namespace App\Http\Requests\Admin;

use App\Models\Estado;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstadoRequest extends FormRequest
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
        /** @var Estado $estado */
        $estado = $this->route('estado');

        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{1,6}$/',
                Rule::unique('estados', 'id_cigam')
                    ->whereNull('deleted_at')
                    ->ignore($estado->id),
            ],
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('estados', 'nome')
                    ->whereNull('deleted_at')
                    ->ignore($estado->id),
            ],
            'abreviacao' => [
                'required',
                'string',
                'size:2',
                Rule::unique('estados', 'abreviacao')
                    ->whereNull('deleted_at')
                    ->ignore($estado->id),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'nome' => 'nome',
            'abreviacao' => 'sigla',
            'descricao' => 'descrição',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_cigam.regex' => 'O ID CIGAM deve conter no máximo 6 dígitos numéricos (zeros à esquerda são aplicados automaticamente).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_cigam' => TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', '')),
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome', '')),
            'abreviacao' => TextoCadastro::normalizarMaiusculas((string) $this->input('abreviacao', '')),
            'descricao' => $this->filled('descricao') ? trim((string) $this->input('descricao')) : null,
        ]);
    }
}
