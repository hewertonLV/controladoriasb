<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileUpdateRequest extends FormRequest
{
    public const AVATAR_MAX_KB = 2048; // alinhado a config('profile.avatar.max_kb')

    protected $errorBag = 'updateProfileInformation';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::AVATAR_MAX_KB],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.image' => 'A foto deve ser uma imagem válida (JPG, PNG ou WebP).',
            'avatar.mimes' => 'Formato não permitido. Use JPG, PNG ou WebP.',
            'avatar.max' => 'A foto não pode ser maior que 2 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'foto de perfil',
        ];
    }

    protected function prepareForValidation(): void
    {
        $contentLength = (int) $this->server('CONTENT_LENGTH', 0);
        $postMaxBytes = $this->iniBytes((string) ini_get('post_max_size'));

        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            throw ValidationException::withMessages([
                'avatar' => 'O envio excede o limite do servidor ('.$this->formatBytes($postMaxBytes).'). Reduza a foto ou aumente post_max_size no PHP.',
            ])->errorBag($this->errorBag);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('avatar');

            if (! $file instanceof UploadedFile) {
                return;
            }

            if (! $file->isValid()) {
                $validator->errors()->add('avatar', $this->phpUploadErrorMessage($file->getError()));

                return;
            }

            if ($file->getSize() > self::AVATAR_MAX_KB * 1024) {
                $validator->errors()->add(
                    'avatar',
                    'A foto não pode ser maior que 2 MB (enviado: '.$this->formatBytes((int) $file->getSize()).').',
                );
            }
        });
    }

    private function phpUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto é grande demais para o servidor. O máximo permitido pelo sistema é 2 MB.',
            UPLOAD_ERR_PARTIAL => 'O envio da foto foi interrompido. Tente novamente.',
            default => 'Não foi possível receber a foto. Tente outro arquivo ou um tamanho menor.',
        };
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' bytes';
    }
}
