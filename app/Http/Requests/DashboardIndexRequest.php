<?php

namespace App\Http\Requests;

use App\Models\UnidadeNegocio;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardIndexRequest extends FormRequest
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
            'unidades' => ['nullable', 'array'],
            'unidades.*' => ['integer', 'exists:unidades_negocio,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if ($user === null) {
                return;
            }

            $access = app(UnidadeNegocioAccessService::class);
            $permitidas = UnidadeNegocio::query()
                ->permitidasPara($user)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ((array) $this->input('unidades', []) as $unidadeId) {
                if (! in_array((int) $unidadeId, $permitidas, true)) {
                    $validator->errors()->add('unidades', 'Unidade de negócio não permitida para o seu usuário.');
                    break;
                }
            }
        });
    }

    /**
     * @return list<int>|null
     */
    public function unidadeIdsFiltro(): ?array
    {
        $unidades = $this->input('unidades');
        if (! is_array($unidades) || $unidades === []) {
            return null;
        }

        return array_values(array_map('intval', $unidades));
    }
}
