<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use Illuminate\Validation\ValidationException;

final class CaptacaoCarteiraService
{
    public function validarUnidades(int $idFaturamento, int $idGalpao): void
    {
        $faturamento = UnidadeNegocio::query()->findOrFail($idFaturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($idGalpao);

        if ($galpao->is_galpao_operacional !== true) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_galpao' => 'A unidade selecionada não é um galpão operacional.',
            ]);
        }

        if ($faturamento->emite_nota_fiscal !== true) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_faturamento' => 'A unidade de faturamento deve emitir nota fiscal.',
            ]);
        }
    }

    public function resolverPorId(int $idCarteira): CaptacaoCarteira
    {
        return CaptacaoCarteira::query()
            ->where('ativo', true)
            ->findOrFail($idCarteira);
    }

    public function possuiClientesVinculados(CaptacaoCarteira $carteira): bool
    {
        return Cliente::query()
            ->where('id_captacao_carteira', $carteira->id)
            ->exists();
    }

    public function inativar(CaptacaoCarteira $carteira): CaptacaoCarteira
    {
        if (! $carteira->ativo) {
            return $carteira;
        }

        if ($this->possuiClientesVinculados($carteira)) {
            throw ValidationException::withMessages([
                'carteira' => 'Não é possível inativar: existem lojas vinculadas a esta carteira.',
            ]);
        }

        $carteira->ativo = false;
        $carteira->save();

        return $carteira->refresh();
    }

    public function reativar(CaptacaoCarteira $carteira): CaptacaoCarteira
    {
        if ($carteira->ativo) {
            return $carteira;
        }

        $carteira->ativo = true;
        $carteira->save();

        return $carteira->refresh();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Cliente>
     */
    public function lojasVinculadas(CaptacaoCarteira $carteira): \Illuminate\Database\Eloquent\Collection
    {
        return Cliente::query()
            ->where('id_captacao_carteira', $carteira->id)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'fantasia', 'id_unidade_negocio']);
    }

    /**
     * Lojas do faturamento da carteira sem vínculo com nenhuma carteira.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Cliente>
     */
    public function lojasSemCarteiraNoFaturamento(CaptacaoCarteira $carteira): \Illuminate\Database\Eloquent\Collection
    {
        return Cliente::query()
            ->where('id_unidade_negocio', $carteira->id_unidade_negocio_faturamento)
            ->whereNull('id_captacao_carteira')
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'fantasia', 'id_unidade_negocio']);
    }

    /**
     * @param  list<int>  $idClientes
     */
    public function sincronizarLojas(CaptacaoCarteira $carteira, array $idClientes): void
    {
        $carteira->refresh();

        $idClientes = collect($idClientes)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $faturamentoId = (int) $carteira->id_unidade_negocio_faturamento;

        if ($idClientes->isNotEmpty()) {
            $elegiveis = Cliente::query()
                ->whereIn('id', $idClientes->all())
                ->get(['id', 'id_captacao_carteira', 'id_unidade_negocio', 'razao_social', 'fantasia']);

            if ($elegiveis->count() !== $idClientes->count()) {
                throw ValidationException::withMessages([
                    'id_clientes' => 'Uma ou mais lojas selecionadas não existem.',
                ]);
            }

            foreach ($elegiveis as $cliente) {
                $carteiraAtual = $cliente->id_captacao_carteira !== null
                    ? (int) $cliente->id_captacao_carteira
                    : null;

                if ($carteiraAtual !== null && $carteiraAtual !== (int) $carteira->id) {
                    $nome = $cliente->fantasia ?: $cliente->razao_social;
                    throw ValidationException::withMessages([
                        'id_clientes' => "A loja «{$nome}» já pertence a outra carteira.",
                    ]);
                }

                if ((int) $cliente->id_unidade_negocio !== $faturamentoId) {
                    $nome = $cliente->fantasia ?: $cliente->razao_social;
                    throw ValidationException::withMessages([
                        'id_clientes' => "A loja «{$nome}» não pertence à unidade de faturamento desta carteira.",
                    ]);
                }
            }
        }

        Cliente::query()
            ->where('id_captacao_carteira', $carteira->id)
            ->when($idClientes->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $idClientes->all()))
            ->update(['id_captacao_carteira' => null]);

        if ($idClientes->isNotEmpty()) {
            Cliente::query()
                ->whereIn('id', $idClientes->all())
                ->update([
                    'id_captacao_carteira' => $carteira->id,
                    'id_unidade_negocio' => $faturamentoId,
                ]);
        }
    }
}
