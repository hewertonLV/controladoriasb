<?php

namespace App\Http\Requests\Admin;

use App\Models\UnidadeNegocio;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMovimentacaoEstoqueRequest extends FormRequest
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
            'id_unidade_negocio' => [
                'required',
                'integer',
                Rule::exists('unidades_negocio', 'id'),
            ],
            'id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'tipo' => ['required', Rule::in(['entrada', 'saida'])],
            'quantidade_kg' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'preco_medio_kg' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'required_if:tipo,entrada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_unidade_negocio' => 'unidade de negócio',
            'id_fruta' => 'fruta',
            'tipo' => 'tipo de movimentação',
            'quantidade_kg' => 'quantidade (kg)',
            'preco_medio_kg' => 'preço médio (kg)',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $idUn = (int) $this->input('id_unidade_negocio', 0);
            if ($idUn <= 0) {
                return;
            }
            $un = UnidadeNegocio::query()->find($idUn);
            if ($un !== null && ! $un->possui_estoque) {
                $v->errors()->add('id_unidade_negocio', 'A unidade selecionada não possui controle de estoque.');
            }

            $user = $this->user();
            if ($user === null || ! app(UnidadeNegocioAccessService::class)->canAccess($user, $idUn)) {
                $v->errors()->add('id_unidade_negocio', UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo' => mb_strtolower(trim((string) $this->input('tipo', ''))),
        ]);
    }
}
