<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\TextoCadastro;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesVeiculoAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function veiculoRules(?int $ignoreId = null): array
    {
        $statusRule = Rule::in(['ATIVO', 'INATIVO']);

        return [
            'id_sbs' => [
                'required',
                'integer',
                'min:1',
                $this->idSbsInteiroPositivoRule(),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255'],
            'id_unidade_negocio' => ['required', 'integer', 'min:1', 'exists:unidades_negocio,id'],
            'status' => ['required', 'string', $statusRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function veiculoAttributes(): array
    {
        return [
            'id_sbs' => 'ID SBS',
            'nome' => 'nome',
            'tipo' => 'tipo',
            'id_unidade_negocio' => 'unidade de negócio',
            'status' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function veiculoMessages(): array
    {
        return [
            'id_sbs.min' => 'O ID SBS deve ser um inteiro positivo.',
        ];
    }

    /**
     * @return Closure(string,mixed,Closure):void
     */
    protected function idSbsInteiroPositivoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $digits = TextoCadastro::normalizarIdSbsInteiroPositivo((string) $value);
            if ($digits === '' || ! ctype_digit($digits) || (int) $digits <= 0) {
                $fail('O ID SBS deve ser um inteiro positivo.');
            }
        };
    }

    protected function prepareVeiculoForValidation(): void
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $idSbs = TextoCadastro::normalizarIdSbsInteiroPositivo((string) $this->input('id_sbs', ''));
        /** @noinspection PhpUndefinedMethodInspection */
        $status = TextoCadastro::normalizarStatusAtivoInativo((string) ($this->input('status', 'ATIVO') ?? 'ATIVO'));

        /** @noinspection PhpUndefinedMethodInspection */
        $this->merge([
            'id_sbs' => $idSbs === '' ? '0' : $idSbs,
            /** @noinspection PhpUndefinedMethodInspection */
            'nome' => trim((string) $this->input('nome')),
            /** @noinspection PhpUndefinedMethodInspection */
            'tipo' => trim((string) $this->input('tipo')),
            /** @noinspection PhpUndefinedMethodInspection */
            'id_unidade_negocio' => (int) $this->input('id_unidade_negocio'),
            'status' => $status,
        ]);
    }
}
