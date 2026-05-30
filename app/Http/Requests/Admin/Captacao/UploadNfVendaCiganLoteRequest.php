<?php

namespace App\Http\Requests\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadNfVendaCiganLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'arquivo_nf_venda' => ['required', 'file', 'max:10240', 'mimes:xml,pdf,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'arquivo_nf_venda' => 'NF de venda',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lote = $this->route('lote');
            if (! $lote instanceof CaptacaoLote) {
                return;
            }

            if ($lote->status !== CaptacaoLoteStatus::FaturamentoCiganIniciado) {
                $validator->errors()->add('status', 'O lote não está na fase de faturamento Cigam iniciado.');
            }
        });
    }
}
