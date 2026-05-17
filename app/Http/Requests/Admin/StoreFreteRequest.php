<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesFreteAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreteRequest extends FormRequest
{
    use ValidatesFreteAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->freteRules(criando: true);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->freteAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFreteForValidation(criando: true);
    }
}
