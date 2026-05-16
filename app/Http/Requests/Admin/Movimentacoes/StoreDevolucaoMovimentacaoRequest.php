<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoDevolucao;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDevolucaoMovimentacaoRequest extends FormRequest
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
        return array_merge($this->camposCalculadosProibidos(), [
            'movimentacao_venda_origem_id' => ['required', 'integer', Rule::exists('movimentacoes', 'id')],
            'tipo_devolucao' => ['required', 'string', Rule::in(TipoDevolucao::values())],
            'id_unidade_negocio_retorno' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->input('tipo_devolucao') === TipoDevolucao::COM_RETORNO_ESTOQUE->value),
                Rule::exists('unidades_negocio', 'id'),
            ],
            'qtd_fruta_um' => ['required', 'numeric', 'min:0.01'],
            'numero_nf_devolucao' => ['required', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:5000'],
            'motivo_devolucao' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $venda = Movimentacao::query()
                ->whereKey((int) $this->input('movimentacao_venda_origem_id'))
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->first();

            if ($venda === null) {
                $v->errors()->add('movimentacao_venda_origem_id', 'Informe uma venda original ativa.');

                return;
            }

            $ignorar = $this->route('movimentacaoDevolucao');
            if (! $ignorar instanceof Movimentacao) {
                $ignorar = null;
            }

            if ((float) $this->input('qtd_fruta_um') > $venda->saldoDevolvivelUm($ignorar) + 1e-6) {
                $v->errors()->add('qtd_fruta_um', 'Quantidade devolvida maior que o saldo devolvível da venda.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $qtd = $this->input('qtd_fruta_um');
        $this->merge([
            'numero_nf_devolucao' => trim((string) $this->input('numero_nf_devolucao')),
            'qtd_fruta_um' => is_string($qtd) && str_contains($qtd, ',')
                ? TextoCadastro::normalizarDecimalNaoNegativo($qtd)
                : number_format(max(0, (float) $qtd), 2, '.', ''),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function camposCalculadosProibidos(): array
    {
        return [
            'id_empresa_origem' => ['prohibited'],
            'id_empresa_destino' => ['prohibited'],
            'id_unidade_negocio_faturamento' => ['prohibited'],
            'devolucao_origem_id' => ['prohibited'],
            'id_fruta' => ['prohibited'],
            'qtd_fruta_kg' => ['prohibited'],
            'categoria_movimentacao_id' => ['prohibited'],
            'status_movimentacao_id' => ['prohibited'],
            'valor_devolucao_total' => ['prohibited'],
            'valor_devolucao_um' => ['prohibited'],
            'valor_devolucao_kg' => ['prohibited'],
            'valor_custo_devolucao' => ['prohibited'],
            'resultado_devolucao' => ['prohibited'],
            'valor_total_movimentacao' => ['prohibited'],
            'valor_nf_total' => ['prohibited'],
            'valor_nf_um' => ['prohibited'],
            'valor_nf_kg' => ['prohibited'],
            'saldo_estoque_fruta_kg' => ['prohibited'],
            'saldo_estoque_fruta_um' => ['prohibited'],
            'preco_medio_fruta_kg' => ['prohibited'],
            'preco_medio_fruta_um' => ['prohibited'],
            'versao' => ['prohibited'],
            'versao_replay' => ['prohibited'],
            'status_registro' => ['prohibited'],
        ];
    }
}
