<?php

namespace App\Http\Requests\Admin;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFrutaRequest extends FormRequest
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
        return array_merge($this->regrasFruta(), $this->regrasIcms());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'nome' => 'nome',
            'unidade_medicao' => 'unidade de medição',
            'kg_por_unidade_medicao' => 'kg por unidade de medição',
            'icms.*.entrada_nacional' => 'ICMS compra nacional',
            'icms.*.entrada_um_nacional' => 'UM ICMS compra nacional',
            'icms.*.entrada_externo' => 'ICMS compra exterior',
            'icms.*.entrada_um_externo' => 'UM ICMS compra exterior',
            'icms.*.saida_importada' => 'ICMS venda fora do estado',
            'icms.*.saida_um_importada' => 'UM ICMS venda fora do estado',
            'icms.*.saida_nacional' => 'ICMS venda dentro do estado',
            'icms.*.saida_um_nacional' => 'UM ICMS venda dentro do estado',
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

    /**
     * @return array<string, mixed>
     */
    public function validatedFruta(): array
    {
        return $this->only([
            'id_cigam',
            'nome',
            'unidade_medicao',
            'kg_por_unidade_medicao',
        ]);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function validatedIcms(): array
    {
        /** @var array<int|string, array<string, mixed>> */
        return $this->input('icms', []);
    }

    protected function prepareForValidation(): void
    {
        $icms = $this->input('icms', []);
        if (is_array($icms)) {
            foreach ($icms as $idEstado => $linha) {
                if (! is_array($linha)) {
                    continue;
                }
                $icms[$idEstado]['entrada_nacional'] = TextoCadastro::normalizarValorMonetarioBrasileiro(
                    $linha['entrada_nacional'] ?? 0,
                );
                $icms[$idEstado]['entrada_um_nacional'] = TextoCadastro::normalizarMaiusculas(
                    (string) ($linha['entrada_um_nacional'] ?? FrutaUmIcms::KG->value),
                );
                $icms[$idEstado]['entrada_externo'] = TextoCadastro::normalizarValorMonetarioBrasileiro(
                    $linha['entrada_externo'] ?? 0,
                );
                $icms[$idEstado]['entrada_um_externo'] = TextoCadastro::normalizarMaiusculas(
                    (string) ($linha['entrada_um_externo'] ?? FrutaUmIcms::KG->value),
                );
                $icms[$idEstado]['saida_importada'] = TextoCadastro::normalizarValorMonetarioBrasileiro(
                    $linha['saida_importada'] ?? 0,
                );
                $icms[$idEstado]['saida_um_importada'] = TextoCadastro::normalizarMaiusculas(
                    (string) ($linha['saida_um_importada'] ?? FrutaUmIcms::KG->value),
                );
                $icms[$idEstado]['saida_nacional'] = TextoCadastro::normalizarValorMonetarioBrasileiro(
                    $linha['saida_nacional'] ?? 0,
                );
                $icms[$idEstado]['saida_um_nacional'] = TextoCadastro::normalizarMaiusculas(
                    (string) ($linha['saida_um_nacional'] ?? FrutaUmIcms::KG->value),
                );
            }
        }

        $this->merge([
            'id_cigam' => TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', '')),
            'nome' => trim((string) $this->input('nome')),
            'unidade_medicao' => mb_strtoupper(trim((string) $this->input('unidade_medicao', '')), 'UTF-8'),
            'kg_por_unidade_medicao' => $this->input('kg_por_unidade_medicao', 0),
            'icms' => $icms,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regrasFruta(): array
    {
        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::unique('frutas', 'id_cigam'),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'unidade_medicao' => ['required', 'string', Rule::in(FrutaUnidadeMedicao::values())],
            'kg_por_unidade_medicao' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regrasIcms(): array
    {
        return [
            'icms' => ['required', 'array'],
            'icms.*.entrada_nacional' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'icms.*.entrada_um_nacional' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'icms.*.entrada_externo' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'icms.*.entrada_um_externo' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'icms.*.saida_importada' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'icms.*.saida_um_importada' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
            'icms.*.saida_nacional' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'icms.*.saida_um_nacional' => ['nullable', 'string', Rule::in(FrutaUmIcms::values())],
        ];
    }
}
