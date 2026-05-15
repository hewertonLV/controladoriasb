<?php

namespace App\Http\Requests\Admin;

use App\Support\TextoCadastro;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFornecedorRequest extends FormRequest
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
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::unique('fornecedores', 'id_cigam'),
            ],
            'id_estado' => ['required', 'integer', 'min:1', Rule::exists('estados', 'id')],
            'razao_social' => ['required', 'string', 'max:255'],
            'fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj_cpf' => [
                'required',
                'string',
                $this->cnpjCpfTamanhoRule(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'id_estado' => 'estado (ICMS)',
            'razao_social' => 'razão social',
            'fantasia' => 'fantasia',
            'cnpj_cpf' => 'CPF/CNPJ',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_cigam.regex' => 'O ID CIGAM deve conter no máximo 6 dígitos numéricos (zeros à esquerda são aplicados automaticamente).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));
        $documento = TextoCadastro::somenteDigitos((string) $this->input('cnpj_cpf', ''));

        $fantasiaBruta = $this->exists('fantasia') ? trim((string) $this->input('fantasia')) : '';

        $this->merge([
            'id_cigam' => $idCigam,
            'id_estado' => (int) $this->input('id_estado', 0),
            'razao_social' => trim((string) $this->input('razao_social')),
            'fantasia' => $fantasiaBruta === '' ? null : $fantasiaBruta,
            'cnpj_cpf' => $documento,
        ]);
    }

    protected function cnpjCpfTamanhoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $len = strlen((string) $value);

            if (! in_array($len, [11, 14], true)) {
                $fail('O CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).');
            }
        };
    }
}
