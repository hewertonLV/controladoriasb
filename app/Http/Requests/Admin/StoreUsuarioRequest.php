<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'login' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'login'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'lowercase',
                Rule::unique('users', 'email'),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('guard_name', 'web'),
            ],
            'unidades_negocio' => ['nullable', 'array'],
            'unidades_negocio.*' => ['integer', Rule::exists('unidades_negocio', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'login' => 'login',
            'email' => 'e-mail',
            'roles' => 'grupos',
            'roles.*' => 'grupo',
            'unidades_negocio' => 'unidades de negócio permitidas',
            'unidades_negocio.*' => 'unidade de negócio permitida',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'O login deve conter apenas letras, números, ponto, hífen ou underline.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'login' => mb_strtolower(trim((string) $this->input('login'))),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
