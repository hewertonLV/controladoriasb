<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class OlhoDeDeusPollRequest extends FormRequest
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
            'since' => ['nullable', 'date'],
            'carga_inicial' => ['nullable', 'boolean'],
        ];
    }

    public function mesReferencia(): ?string
    {
        $mes = $this->input('mes');

        return is_string($mes) && $mes !== '' ? $mes : null;
    }

    public function sinceCursor(): ?Carbon
    {
        $since = $this->input('since');

        if (! is_string($since) || $since === '') {
            return null;
        }

        try {
            return Carbon::parse($since);
        } catch (\Throwable) {
            return null;
        }
    }

    public function cargaInicial(): bool
    {
        return $this->boolean('carga_inicial');
    }
}
