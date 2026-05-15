<?php

namespace App\Http\Requests\Auth;

use App\Http\Controllers\Admin\UsuarioController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && $value === UsuarioController::DEFAULT_PASSWORD) {
                        $fail('A nova senha não pode ser igual à senha padrão do sistema.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'nova senha',
            'password_confirmation' => 'confirmação da nova senha',
        ];
    }
}
