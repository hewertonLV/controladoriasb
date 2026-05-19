<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\FreteStatusSituacao;
use App\Support\TextoCadastro;
use Illuminate\Validation\Rule;

trait ValidatesFreteAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function freteRules(?int $ignoreId = null, bool $criando = false): array
    {
        $nomeUnique = Rule::unique('fretes', 'nome');
        if ($ignoreId !== null) {
            $nomeUnique = $nomeUnique->ignore($ignoreId);
        }

        return [
            'nome' => ['required', 'string', 'max:255', $nomeUnique],
            'valor' => ['required', 'numeric', 'min:0'],
            'id_veiculo' => ['required', 'integer', 'min:1', 'exists:veiculos,id'],
            'descricao' => ['nullable', 'string'],
            'status_situacao' => [$criando ? 'nullable' : 'required', 'string', Rule::in(FreteStatusSituacao::values())],
            'valor_fruta_kg' => [$criando ? 'nullable' : 'required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function freteAttributes(): array
    {
        return [
            'nome' => 'nome',
            'valor' => 'valor',
            'id_veiculo' => 'veículo',
            'descricao' => 'descrição',
            'status_situacao' => 'situação',
            'valor_fruta_kg' => 'valor fruta/kg',
        ];
    }

    protected function prepareFreteForValidation(bool $criando = false): void
    {
        $valor = TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('valor'));

        /** @noinspection PhpUndefinedMethodInspection */
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome')),
            'valor' => $valor,
            /** @noinspection PhpUndefinedMethodInspection */
            'id_veiculo' => (int) $this->input('id_veiculo'),
            /** @noinspection PhpUndefinedMethodInspection */
            'descricao' => $this->filled('descricao') ? trim((string) $this->input('descricao')) : null,
            'status_situacao' => $criando
                ? FreteStatusSituacao::ABERTA->value
                : mb_strtoupper(trim((string) ($this->input('status_situacao') ?: FreteStatusSituacao::ABERTA->value)), 'UTF-8'),
            'valor_fruta_kg' => $criando
                ? $valor
                : TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('valor_fruta_kg')),
        ]);
    }
}
