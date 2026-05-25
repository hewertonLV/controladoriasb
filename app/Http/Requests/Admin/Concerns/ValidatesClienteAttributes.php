<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Praca;
use App\Support\TextoCadastro;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesClienteAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clienteRules(?int $ignoreId = null): array
    {
        $uniqueIdCigam = function () use ($ignoreId): mixed {
            $rule = Rule::unique('clientes', 'id_cigam');
            if ($ignoreId !== null) {
                $rule = $rule->ignore($ignoreId);
            }

            return $rule;
        };

        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\\d{6}$/',
                $uniqueIdCigam(),
            ],
            'razao_social' => ['required', 'string', 'max:255'],
            'fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj_cpf' => [
                'required',
                'string',
                $this->cpfCnpjLengthRule(),
            ],
            'id_unidade_negocio' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('unidades_negocio', 'id')->where(static fn ($query) => $query->where('is_hub', false)),
            ],
            'id_praca' => [
                'required',
                'integer',
                'min:1',
                'exists:pracas,id',
                $this->pracaPertenceUnidadeRule(),
            ],
            'grupo_id' => ['nullable', 'integer', 'min:1', 'exists:grupos,id'],
            'desconto_nf' => [
                'required',
                'numeric',
                'min:0',
                'regex:/^\\d+(\\.\\d{1,2})?$/',
            ],
            'percentual_margem_alvo' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'id_captacao_carteira' => [
                'nullable',
                'integer',
                Rule::exists('captacao_carteiras', 'id')->where('ativo', true),
            ],
            'dias_criacao_pedido' => ['nullable', 'array'],
            'dias_criacao_pedido.*' => ['integer', 'min:0', 'max:6'],
            'dias_envio_pedido' => ['nullable', 'array'],
            'dias_envio_pedido.*' => ['integer', 'min:0', 'max:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clienteAttributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'razao_social' => 'razão social',
            'fantasia' => 'fantasia',
            'cnpj_cpf' => 'CPF/CNPJ',
            'id_unidade_negocio' => 'unidade de negócio',
            'id_praca' => 'praça',
            'grupo_id' => 'grupo',
            'desconto_nf' => 'desconto NF',
            'percentual_margem_alvo' => 'margem alvo captação',
            'id_captacao_carteira' => 'carteira de captação',
            'dias_criacao_pedido' => 'dias de criação do pedido',
            'dias_envio_pedido' => 'dias de envio do pedido',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clienteMessages(): array
    {
        return [
            'desconto_nf.min' => 'O desconto não pode ser negativo.',
            'id_unidade_negocio.exists' => 'Selecione uma unidade de negócio válida (unidades HUB não são permitidas).',
        ];
    }

    protected function prepareClienteForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));
        $documento = TextoCadastro::somenteDigitos((string) $this->input('cnpj_cpf', ''));
        $descontoNf = $this->input('desconto_nf');
        $fantasia = preg_replace('/\s+/u', ' ', (string) $this->input('fantasia', '')) ?? '';

        $grupoId = $this->input('grupo_id');

        $this->merge([
            'id_cigam' => $idCigam,
            'razao_social' => trim((string) $this->input('razao_social')),
            'fantasia' => TextoCadastro::normalizarMaiusculasOuNulo($fantasia),
            'cnpj_cpf' => $documento,
            'id_unidade_negocio' => (int) $this->input('id_unidade_negocio'),
            'id_praca' => (int) $this->input('id_praca'),
            'grupo_id' => $grupoId === null || $grupoId === '' ? null : (int) $grupoId,
            'desconto_nf' => $descontoNf === null || $descontoNf === '' ? '0.00' : (string) $descontoNf,
            'id_captacao_carteira' => $this->filled('id_captacao_carteira')
                ? (int) $this->input('id_captacao_carteira')
                : null,
            'dias_criacao_pedido' => array_values(array_map('intval', (array) $this->input('dias_criacao_pedido', []))),
            'dias_envio_pedido' => array_values(array_map('intval', (array) $this->input('dias_envio_pedido', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dadosClientePersistencia(): array
    {
        $dados = $this->validated();
        unset($dados['dias_criacao_pedido'], $dados['dias_envio_pedido']);

        if (! empty($dados['id_captacao_carteira'])) {
            $carteira = \App\Models\Captacao\CaptacaoCarteira::query()->find((int) $dados['id_captacao_carteira']);
            if ($carteira !== null) {
                $dados['id_unidade_negocio'] = (int) $carteira->id_unidade_negocio_faturamento;
            }
        }

        return $dados;
    }

    protected function cpfCnpjLengthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $len = strlen((string) $value);

            if (! in_array($len, [11, 14], true)) {
                $fail('O CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).');
            }
        };
    }

    protected function pracaPertenceUnidadeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $idPraca = (int) $value;
            $idUnidade = (int) $this->input('id_unidade_negocio');

            if ($idPraca < 1 || $idUnidade < 1) {
                return;
            }

            $valida = Praca::query()
                ->whereKey($idPraca)
                ->where('id_unidade_negocio', $idUnidade)
                ->exists();

            if (! $valida) {
                $fail('A praça selecionada não pertence à unidade de negócio informada.');
            }
        };
    }
}
