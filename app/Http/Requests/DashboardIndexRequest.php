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
            'unidades.*' => ['nullable', 'integer', 'exists:unidades_negocio,id'],
            'sem_unidades' => ['nullable', 'boolean'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'dia' => ['nullable', 'date_format:Y-m-d'],
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
     * @return list<int>|null null na carga inicial (sem parâmetro na URL); array vazio = nenhuma unidade ativa
     */
    public function unidadeIdsFiltro(): ?array
    {
        if (! $this->has('unidades') && ! $this->boolean('sem_unidades')) {
            return null;
        }

        if ($this->boolean('sem_unidades')) {
            return [];
        }

        $unidades = array_values(array_filter(
            array_map(static fn ($id): int => (int) $id, (array) $this->input('unidades', [])),
            static fn (int $id): bool => $id > 0,
        ));

        return $unidades;
    }

    public function mesReferencia(): ?string
    {
        $mes = $this->input('mes');

        return is_string($mes) && $mes !== '' ? $mes : null;
    }

    public function diaReferencia(): ?string
    {
        $dia = $this->input('dia');

        return is_string($dia) && $dia !== '' ? $dia : null;
    }
}
