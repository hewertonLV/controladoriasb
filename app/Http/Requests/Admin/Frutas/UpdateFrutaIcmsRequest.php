<?php

namespace App\Http\Requests\Admin\Frutas;

use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;

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
        $rules = [];

        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $rules[$chave] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
        }

        return $rules;
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
        return $this->only(FrutaIcmsLinhaFormulario::chaves());
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $merge[$chave] = TextoCadastro::normalizarValorMonetarioBrasileiro($this->input($chave, 0));
        }

        $this->merge($merge);
    }
}
