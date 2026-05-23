<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesUnidadeNegocioAttributes;
use App\Models\UnidadeNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUnidadeNegocioRequest extends FormRequest
{
    use ValidatesUnidadeNegocioAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var UnidadeNegocio $unidade */
        $unidade = $this->route('unidadeNegocio');

        return $this->unidadeNegocioRules($unidade->id);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->unidadeNegocioAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->unidadeNegocioMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareUnidadeNegocioForValidation();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validarCombinacaoFlagsUnidadeNegocio($validator);
    }
}
