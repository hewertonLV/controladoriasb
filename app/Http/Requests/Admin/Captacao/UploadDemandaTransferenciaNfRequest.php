<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class UploadDemandaTransferenciaNfRequest extends FormRequest
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
            'arquivo_nf' => ['required', 'file', 'max:10240', 'mimes:xml,pdf,txt'],
        ];
    }
}
