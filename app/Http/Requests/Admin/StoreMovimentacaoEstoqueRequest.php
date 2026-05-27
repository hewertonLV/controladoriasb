<?php

namespace App\Http\Requests\Admin;

use App\Models\UnidadeNegocio;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\TextoCadastro;
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
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id_fruta' => [
                'required',
                'integer',
                Rule::exists('frutas', 'id')->where(
                    fn ($query) => $query->where('kg_por_unidade_medicao', '>', 0),
                ),
            ],
            'itens.*.qtd_fruta_um' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'itens.*.preco_fruta_um' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_unidade_negocio' => 'unidade de negócio',
            'itens' => 'itens',
            'itens.*.id_fruta' => 'fruta',
            'itens.*.qtd_fruta_um' => 'quantidade (UM)',
            'itens.*.preco_fruta_um' => 'preço (UM)',
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
        $itens = $this->input('itens', []);
        if (! is_array($itens)) {
            return;
        }

        foreach ($itens as $key => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (array_key_exists('qtd_fruta_um', $item)) {
                $itens[$key]['qtd_fruta_um'] = TextoCadastro::normalizarDecimalNaoNegativo($item['qtd_fruta_um']);
            }
            if (array_key_exists('preco_fruta_um', $item)) {
                $precoRaw = $item['preco_fruta_um'];
                $itens[$key]['preco_fruta_um'] = is_string($precoRaw) && str_contains($precoRaw, ',')
                    ? TextoCadastro::normalizarValorMonetarioBrasileiro($precoRaw)
                    : TextoCadastro::normalizarDecimalNaoNegativo($precoRaw);
            }
        }

        $this->merge(['itens' => $itens]);
    }
}
