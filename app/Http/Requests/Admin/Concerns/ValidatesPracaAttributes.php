<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\TextoCadastro;
use Illuminate\Validation\Rule;

trait ValidatesPracaAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function pracaRules(?int $ignoreId = null): array
    {
        $nomeUnique = Rule::unique('pracas', 'nome')
            ->where(fn ($query) => $query->where(
                'id_unidade_negocio',
                (int) $this->input('id_unidade_negocio'),
            ));

        if ($ignoreId !== null) {
            $nomeUnique = $nomeUnique->ignore($ignoreId);
        }

        return [
            'nome' => ['required', 'string', 'max:255', $nomeUnique],
            'id_unidade_negocio' => ['required', 'integer', 'min:1', 'exists:unidades_negocio,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pracaAttributes(): array
    {
        return [
            'nome' => 'nome',
            'id_unidade_negocio' => 'unidade de negócio',
        ];
    }

    protected function preparePracaForValidation(): void
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->merge([
            'nome' => TextoCadastro::normalizarMaiusculas((string) $this->input('nome')),
            /** @noinspection PhpUndefinedMethodInspection */
            'id_unidade_negocio' => (int) $this->input('id_unidade_negocio'),
        ]);
    }
}
