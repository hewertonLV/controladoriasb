<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @mixin FormRequest
 */
trait ValidatesStatusMovimentacaoAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function statusMovimentacaoRules(?int $ignoreId = null): array
    {
        $nomeUnique = Rule::unique('status_movimentacoes', 'nome');

        if ($ignoreId !== null) {
            $nomeUnique = $nomeUnique->ignore($ignoreId);
        }

        return [
            'nome' => ['required', 'string', 'max:255', $nomeUnique],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function statusMovimentacaoAttributes(): array
    {
        return [
            'nome' => 'nome',
        ];
    }

    protected function prepareStatusMovimentacaoForValidation(): void
    {
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome', '')),
        ]);
    }
}
