<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesClienteAttributes;
use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
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
        /** @var Cliente $cliente */
        $cliente = $this->route('cliente');

        return $this->clienteRules($cliente->id);
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
