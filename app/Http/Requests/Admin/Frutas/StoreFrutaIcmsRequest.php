<?php

namespace App\Http\Requests\Admin\Frutas;

use App\Models\FrutaIcmsAliquota;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFrutaIcmsRequest extends FormRequest
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
        $rules = [
            'fruta_id' => ['required', 'integer', 'exists:frutas,id'],
            'id_estado' => ['required', 'integer', 'exists:estados,id'],
        ];

        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $rules[$chave] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_estado.unique' => 'Já existe ICMS cadastrado para esta fruta neste estado. Use editar na listagem.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fruta_id' => 'fruta',
            'id_estado' => 'estado',
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => 'Entrada nacional (R$/kg)',
            FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => 'Entrada internacional (R$/kg)',
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => 'Venda nacional dentro do estado (%)',
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => 'Venda nacional fora do estado (%)',
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => 'Venda internacional dentro do estado (%)',
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => 'Venda internacional fora do estado (%)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dadosIcms(): array
    {
        return $this->only(FrutaIcmsLinhaFormulario::chaves());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $frutaId = (int) $this->input('fruta_id');
            $idEstado = (int) $this->input('id_estado');

            if ($frutaId > 0 && $idEstado > 0
                && FrutaIcmsAliquota::query()
                    ->where('fruta_id', $frutaId)
                    ->where('id_estado', $idEstado)
                    ->exists()) {
                $validator->errors()->add(
                    'id_estado',
                    'Já existe ICMS cadastrado para esta fruta neste estado. Use editar na listagem.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $merge[$chave] = TextoCadastro::normalizarValorMonetarioBrasileiro($this->input($chave, 0));
        }

        $this->merge($merge);
    }
}
