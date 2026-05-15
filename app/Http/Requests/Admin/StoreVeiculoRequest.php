<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesVeiculoAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreVeiculoRequest extends FormRequest
{
    use ValidatesVeiculoAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->veiculoRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->veiculoAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->veiculoMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareVeiculoForValidation();
    }
}
