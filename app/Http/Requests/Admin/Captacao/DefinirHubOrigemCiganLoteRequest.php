<?php

namespace App\Http\Requests\Admin\Captacao;

use App\Models\Captacao\CaptacaoLote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DefinirHubOrigemCiganLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('captacao.lote.transferencia.iniciar') ?? false;
    }

    public function rules(): array
    {
        /** @var CaptacaoLote $lote */
        $lote = $this->route('lote');

        return [
            'id_unidade_negocio_hub_origem' => [
                'required',
                'integer',
                Rule::exists('unidades_negocio', 'id')
                    ->where('is_hub', true)
                    ->where('status', true),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var CaptacaoLote|null $lote */
            $lote = $this->route('lote');
            if ($lote === null) {
                return;
            }

            if (! $lote->status->exibeAbaArquivoCiganTransferencia()) {
                $validator->errors()->add('status', 'O lote não está na fase de transferência Cigan iniciada.');
            }
        });
    }
}
