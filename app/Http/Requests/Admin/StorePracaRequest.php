<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPracaAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StorePracaRequest extends FormRequest
{
    use ValidatesPracaAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->pracaRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->pracaAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->preparePracaForValidation();
    }
}
