<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @mixin FormRequest
 */
trait ValidatesCategoriaMovimentacaoAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function categoriaMovimentacaoRules(?int $ignoreId = null): array
    {
        $nomeUnique = Rule::unique('categorias_movimentacao', 'nome');

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
    protected function categoriaMovimentacaoAttributes(): array
    {
        return [
            'nome' => 'nome',
        ];
    }

    protected function prepareCategoriaMovimentacaoForValidation(): void
    {
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome', '')),
        ]);
    }
}
