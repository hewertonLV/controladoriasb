<?php

namespace App\Http\Requests\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFrutaIcmsRequest extends FormRequest
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
            'fruta_id' => ['required', 'integer', 'exists:frutas,id'],
            'id_estado' => [
                'required',
                'integer',
                'exists:estados,id',
                Rule::unique('fruta_icms', 'id_estado')
                    ->where('fruta_id', $this->input('fruta_id'))
                    ->where('operacao', 'ENTRADA'),
            ],
            'entrada_nacional' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'entrada_um_nacional' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'entrada_externo' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'entrada_um_externo' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'saida_importada' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'saida_um_importada' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'saida_nacional' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'saida_um_nacional' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_estado.unique' => 'Já existe ICMS cadastrado para esta fruta neste estado. Use editar na listagem.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fruta_id' => 'fruta',
            'id_estado' => 'estado',
            'entrada_nacional' => 'ICMS compra nacional',
            'entrada_um_nacional' => 'UM compra nacional',
            'entrada_externo' => 'ICMS compra exterior',
            'entrada_um_externo' => 'UM compra exterior',
            'saida_importada' => 'ICMS venda importada',
            'saida_um_importada' => 'UM venda importada',
            'saida_nacional' => 'ICMS venda nacional',
            'saida_um_nacional' => 'UM venda nacional',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dadosIcms(): array
    {
        return $this->only([
            'entrada_nacional',
            'entrada_um_nacional',
            'entrada_externo',
            'entrada_um_externo',
            'saida_importada',
            'saida_um_importada',
            'saida_nacional',
            'saida_um_nacional',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entrada_nacional' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('entrada_nacional', 0)),
            'entrada_um_nacional' => TextoCadastro::normalizarMaiusculas((string) ($this->input('entrada_um_nacional', FrutaUmIcms::KG->value))),
            'entrada_externo' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('entrada_externo', 0)),
            'entrada_um_externo' => TextoCadastro::normalizarMaiusculas((string) ($this->input('entrada_um_externo', FrutaUmIcms::KG->value))),
            'saida_importada' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('saida_importada', 0)),
            'saida_um_importada' => TextoCadastro::normalizarMaiusculas((string) ($this->input('saida_um_importada', FrutaUmIcms::KG->value))),
            'saida_nacional' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('saida_nacional', 0)),
            'saida_um_nacional' => TextoCadastro::normalizarMaiusculas((string) ($this->input('saida_um_nacional', FrutaUmIcms::KG->value))),
        ]);
    }
}
