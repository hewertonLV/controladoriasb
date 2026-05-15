<?php

namespace App\Http\Requests\Admin;

use App\Models\Empresa;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaRequest extends FormRequest
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
        /** @var Empresa $empresa */
        $empresa = $this->route('empresa');

        return [
            'id_cigam' => [
                'required',
                'string',
                'max:50',
                Rule::unique('empresas', 'id_cigam')->ignore($empresa->id),
            ],
            'status' => ['required', 'boolean'],
            'nome' => ['required', 'string', 'max:255'],
            'fantasia' => ['nullable', 'string', 'max:255'],
            'tipo_pessoa' => ['required', Rule::in(Empresa::tiposPessoa())],
            'cpf_cnpj' => [
                'required',
                'string',
                Rule::unique('empresas', 'cpf_cnpj')->ignore($empresa->id),
                $this->cpfCnpjLengthRule(),
            ],
            'unidade_negocio' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'status' => 'status',
            'nome' => 'nome / razão social',
            'fantasia' => 'nome fantasia',
            'cpf_cnpj' => 'CPF/CNPJ',
            'unidade_negocio' => 'unidade de negócio',
            'tipo_pessoa' => 'tipo de pessoa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = strtoupper(trim((string) $this->input('tipo_pessoa', '')));
        $documento = preg_replace('/\D/', '', (string) $this->input('cpf_cnpj', '')) ?? '';

        $this->merge([
            'id_cigam' => trim((string) $this->input('id_cigam')),
            'nome' => trim((string) $this->input('nome')),
            'fantasia' => $this->filled('fantasia') ? trim((string) $this->input('fantasia')) : null,
            'tipo_pessoa' => $tipo,
            'cpf_cnpj' => $documento,
            'status' => $this->boolean('status'),
        ]);
    }

    protected function cpfCnpjLengthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tipo = strtoupper((string) $this->input('tipo_pessoa', ''));
            $len = strlen((string) $value);

            if ($tipo === Empresa::TIPO_PESSOA_FISICA && $len !== 11) {
                $fail('Para pessoa física, o CPF deve ter 11 dígitos.');
            }

            if ($tipo === Empresa::TIPO_PESSOA_JURIDICA && $len !== 14) {
                $fail('Para pessoa jurídica, o CNPJ deve ter 14 dígitos.');
            }
        };
    }
}
