<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPracaAttributes;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePracaRequest extends FormRequest
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
        return $this->pracaRules($this->route('praca')?->id);
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
