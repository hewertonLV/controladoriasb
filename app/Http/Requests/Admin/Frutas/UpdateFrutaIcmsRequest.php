<?php

namespace App\Http\Requests\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFrutaIcmsRequest extends FormRequest
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
    public function attributes(): array
    {
        return (new StoreFrutaIcmsRequest)->attributes();
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
