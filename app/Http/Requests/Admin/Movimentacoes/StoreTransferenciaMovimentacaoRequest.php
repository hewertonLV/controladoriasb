<?php

namespace App\Http\Requests\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Http\Requests\Admin\Concerns\ValidatesMovimentacaoAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferenciaMovimentacaoRequest extends FormRequest
{
    use ValidatesMovimentacaoAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->movimentacaoBaseRules(CategoriaMovimentacaoTipo::Transferencia);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->movimentacaoBaseAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Transferencia->value]);
        $this->prepareMovimentacaoForValidation();
    }
}
