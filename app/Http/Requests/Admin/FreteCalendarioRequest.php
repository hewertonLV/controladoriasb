<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FreteCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'mes' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function mesReferencia(): ?string
    {
        $mes = $this->input('mes');

        return is_string($mes) && $mes !== '' ? $mes : null;
    }
}
