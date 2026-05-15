<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesFreteAttributes;
use App\Models\Frete;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFreteRequest extends FormRequest
{
    use ValidatesFreteAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Frete $frete */
        $frete = $this->route('frete');

        return $this->freteRules($frete->id);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->freteAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFreteForValidation();
    }
}
