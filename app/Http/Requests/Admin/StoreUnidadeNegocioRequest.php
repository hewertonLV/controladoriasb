<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesUnidadeNegocioAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnidadeNegocioRequest extends FormRequest
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
        return $this->unidadeNegocioRules();
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
}
