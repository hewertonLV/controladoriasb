<?php

namespace App\Http\Requests\Admin;

use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUnidadeMedicao;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
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
            'procedencia' => 'procedência da fruta',
            'icms.*.entrada_nacional_kg' => 'ICMS entrada nacional (R$/kg)',
            'icms.*.entrada_internacional_kg' => 'ICMS entrada internacional (R$/kg)',
            'icms.*.saida_nacional_dentro_pct' => 'ICMS venda nacional dentro (%)',
            'icms.*.saida_nacional_fora_pct' => 'ICMS venda nacional fora (%)',
            'icms.*.saida_internacional_dentro_pct' => 'ICMS venda internacional dentro (%)',
            'icms.*.saida_internacional_fora_pct' => 'ICMS venda internacional fora (%)',
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
            'procedencia',
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
                $linha = FrutaIcmsLinhaFormulario::normalizarChavesLegadas($linha);
                foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
                    $icms[$idEstado][$chave] = TextoCadastro::normalizarValorMonetarioBrasileiro($linha[$chave] ?? 0);
                }
            }
        }

        $this->merge([
            'id_cigam' => TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', '')),
            'nome' => trim((string) $this->input('nome')),
            'unidade_medicao' => mb_strtoupper(trim((string) $this->input('unidade_medicao', '')), 'UTF-8'),
            'kg_por_unidade_medicao' => $this->input('kg_por_unidade_medicao', 0),
            'procedencia' => mb_strtoupper(trim((string) $this->input('procedencia', FrutaProcedencia::NACIONAL->value)), 'UTF-8'),
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
            'procedencia' => ['required', 'string', Rule::in(FrutaProcedencia::values())],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regrasIcms(): array
    {
        $regras = [
            'icms' => ['required', 'array'],
        ];

        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $regras['icms.*.'.$chave] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
        }

        return $regras;
    }
}
