<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesClienteAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    use ValidatesClienteAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->clienteRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->clienteAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->clienteMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareClienteForValidation();
    }
}
