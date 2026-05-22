<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Http\Requests\Admin\Movimentacoes\Concerns\ValidaAcessoUnidadeNegocio;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEntradaEstoqueMovimentacaoRequest extends FormRequest
{
    use ValidaAcessoUnidadeNegocio;

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
            'id_empresa_origem' => ['required', 'integer', Rule::exists('empresas', 'id')->where('entidade_type', UnidadeNegocio::class)],
            'observacao' => ['nullable', 'string', 'max:5000'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.id_fruta' => ['required', 'integer', Rule::exists('frutas', 'id')->where(fn ($q) => $q->where('kg_por_unidade_medicao', '>', 0))],
            'itens.*.qtd_fruta_um' => ['required', 'integer', 'min:1'],
            'itens.*.preco_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'id_movimentacao_estoque_old' => ['prohibited'],
            'id_movimentacao_estoque_new' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'valor_nf_total' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
            'valor_total_movimentacao' => ['prohibited'],
            'preco_medio_fruta_kg' => ['prohibited'],
            'preco_medio_fruta_um' => ['prohibited'],
            'categoria_movimentacao_id' => ['prohibited'],
            'status_movimentacao_id' => ['prohibited'],
            'data_movimentacao' => ['prohibited'],
            'versao' => ['prohibited'],
            'status_registro' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validarAcessoEmpresaUnidade($v, 'id_empresa_origem', 'EntradaEstoque');

            foreach ((array) $this->input('itens', []) as $i => $item) {
                if (! isset($item['id_fruta'])) {
                    continue;
                }
                $fruta = Fruta::query()->find((int) $item['id_fruta']);
                if ($fruta !== null && (float) $fruta->kg_por_unidade_medicao <= 0) {
                    $v->errors()->add("itens.{$i}.id_fruta", 'A fruta precisa ter kg por unidade de medição maior que zero.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $itens = [];
        foreach ((array) $this->input('itens', []) as $key => $item) {
            if (
                blank($item['id_fruta'] ?? null)
                && blank($item['qtd_fruta_um'] ?? null)
                && blank($item['preco_fruta_um'] ?? null)
            ) {
                continue;
            }

            $itens[$key] = $item;
            if (array_key_exists('qtd_fruta_um', $item)) {
                $qtdRaw = trim((string) $item['qtd_fruta_um']);
                if (str_contains($qtdRaw, ',')) {
                    $itens[$key]['qtd_fruta_um'] = $qtdRaw;
                } else {
                    $digits = preg_replace('/\D/', '', $qtdRaw);
                    $itens[$key]['qtd_fruta_um'] = $digits === '' ? '' : (string) (int) $digits;
                }
            }
            if (array_key_exists('preco_fruta_um', $item)) {
                $precoRaw = $item['preco_fruta_um'];
                $itens[$key]['preco_fruta_um'] = is_string($precoRaw) && str_contains($precoRaw, ',')
                    ? TextoCadastro::normalizarValorMonetarioBrasileiro($precoRaw)
                    : number_format(max(0, (float) $precoRaw), 2, '.', '');
            }
        }

        $this->merge(['itens' => $itens]);
    }
}
