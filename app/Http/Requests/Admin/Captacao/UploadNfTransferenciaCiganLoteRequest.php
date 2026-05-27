<?php

namespace App\Http\Requests\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadNfTransferenciaCiganLoteRequest extends FormRequest
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
            'arquivo_nf_transferencia' => ['required', 'file', 'max:10240', 'mimes:xml,pdf,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'arquivo_nf_transferencia' => 'NF de transferência',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lote = $this->route('lote');
            if (! $lote instanceof CaptacaoLote) {
                return;
            }

            if ($lote->status !== CaptacaoLoteStatus::TransferenciaCiganIniciada) {
                $validator->errors()->add('status', 'O lote não está na fase de transferência Cigam iniciada.');
            }

            if ($lote->id_unidade_negocio_hub_origem === null) {
                $validator->errors()->add(
                    'id_unidade_negocio_hub_origem',
                    'Informe e salve o HUB de origem antes de enviar a NF.',
                );
            }
        });
    }
}
