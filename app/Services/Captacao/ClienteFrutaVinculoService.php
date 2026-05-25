<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Cliente;
use App\Models\Fruta;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ClienteFrutaVinculoService
{
    /**
     * @param  list<int>  $idFrutas
     */
    /**
     * Adiciona frutas ao vínculo da loja sem remover as já cadastradas.
     *
     * @param  list<int>  $idFrutas
     */
    public function adicionarFrutas(Cliente $cliente, array $idFrutas): void
    {
        foreach (collect($idFrutas)->map(fn ($id) => (int) $id)->unique() as $idFruta) {
            if ($idFruta < 1) {
                continue;
            }

            ClienteFrutaVinculo::query()->updateOrCreate(
                [
                    'id_cliente' => $cliente->id,
                    'id_fruta' => $idFruta,
                ],
                ['ativo' => true],
            );
        }
    }

    /**
     * @param  list<int>  $idFrutas
     */
    public function sincronizarFrutas(Cliente $cliente, array $idFrutas): void
    {
        $idFrutas = collect($idFrutas)->map(fn ($id) => (int) $id)->unique()->values();

        $existentes = ClienteFrutaVinculo::query()
            ->where('id_cliente', $cliente->id)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id);

        $remover = $existentes->diff($idFrutas);
        if ($remover->isNotEmpty()) {
            ClienteFrutaVinculo::query()
                ->where('id_cliente', $cliente->id)
                ->whereIn('id_fruta', $remover->all())
                ->delete();
        }

        foreach ($idFrutas as $idFruta) {
            ClienteFrutaVinculo::query()->updateOrCreate(
                [
                    'id_cliente' => $cliente->id,
                    'id_fruta' => $idFruta,
                ],
                ['ativo' => true],
            );
        }
    }

    public function clientePossuiFruta(int $idCliente, int $idFruta): bool
    {
        return ClienteFrutaVinculo::query()
            ->where('id_cliente', $idCliente)
            ->where('id_fruta', $idFruta)
            ->where('ativo', true)
            ->exists();
    }

    /**
     * @return Collection<int, Fruta>
     */
    public function frutasVinculadasDoCliente(int $idCliente): Collection
    {
        $ids = ClienteFrutaVinculo::query()
            ->where('id_cliente', $idCliente)
            ->where('ativo', true)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Fruta::query()
            ->whereIn('id', $ids->all())
            ->orderBy('nome')
            ->get();
    }

    /**
     * Lojas já incluídas na matriz do lote + união de colunas de frutas vinculadas.
     *
     * @return array{
     *     clientes: Collection<int, Cliente>,
     *     frutas: Collection<int, Fruta>,
     *     frutasPorCliente: array<int, list<int>>,
     *     clientesDisponiveis: Collection<int, Cliente>,
     *     layout_hash: string,
     * }
     */
    public function dadosMatriz(CaptacaoLote $lote): array
    {
        $lote->load(['pedidos.itens']);

        $clienteIds = $lote->pedidos
            ->sortBy('id')
            ->pluck('id_cliente')
            ->unique()
            ->values();

        if ($clienteIds->isEmpty()) {
            $clientes = collect();
        } else {
            $clientesPorId = Cliente::query()
                ->where('id_unidade_negocio', $lote->id_unidade_negocio_faturamento)
                ->whereIn('id', $clienteIds)
                ->get(['id', 'razao_social', 'fantasia'])
                ->keyBy('id');

            $clientes = $clienteIds
                ->map(fn (int $idCliente) => $clientesPorId->get($idCliente))
                ->filter()
                ->values();
        }

        $frutasPorCliente = $this->mapaFrutasPorClientes($clientes->pluck('id')->all());

        $frutaIds = collect($frutasPorCliente)->flatten()->unique()->values();
        $frutas = $frutaIds->isEmpty()
            ? collect()
            : Fruta::query()
                ->whereIn('id', $frutaIds)
                ->orderBy('nome')
                ->get(['id', 'nome']);

        $clientesDisponiveis = $this->clientesDisponiveisParaMatriz($lote);

        return [
            'clientes' => $clientes,
            'frutas' => $frutas,
            'frutasPorCliente' => $frutasPorCliente,
            'clientesDisponiveis' => $clientesDisponiveis,
            'layout_hash' => $this->layoutHash($clientes, $frutas),
        ];
    }

    /**
     * @return Collection<int, Cliente>
     */
    public function clientesDisponiveisParaMatriz(CaptacaoLote $lote): Collection
    {
        $lote->loadMissing('pedidos');

        $idsNoLote = $lote->pedidos->pluck('id_cliente')->unique();

        $query = Cliente::query()
            ->when($idsNoLote->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $idsNoLote))
            ->orderBy('razao_social');

        if ($lote->id_captacao_carteira !== null) {
            $query->where('id_captacao_carteira', $lote->id_captacao_carteira);
        } else {
            $query->where('id_unidade_negocio', $lote->id_unidade_negocio_faturamento);
        }

        return $query->get(['id', 'razao_social', 'fantasia']);
    }

    public function assertClienteElegivelParaMatriz(CaptacaoLote $lote, int $idCliente): Cliente
    {
        $cliente = Cliente::query()->findOrFail($idCliente);

        if ($lote->id_captacao_carteira !== null) {
            if ((int) $cliente->id_captacao_carteira !== (int) $lote->id_captacao_carteira) {
                throw ValidationException::withMessages([
                    'id_cliente' => 'A loja não pertence à carteira deste lote.',
                ]);
            }
        } elseif ((int) $cliente->id_unidade_negocio !== (int) $lote->id_unidade_negocio_faturamento) {
            throw ValidationException::withMessages([
                'id_cliente' => 'A loja não pertence à unidade de faturamento deste lote.',
            ]);
        }

        if (! ClienteFrutaVinculo::query()
            ->where('id_cliente', $cliente->id)
            ->where('ativo', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Vincule ao menos uma fruta a esta loja antes de incluí-la na matriz.',
            ]);
        }

        if ($lote->pedidos()->where('id_cliente', $cliente->id)->exists()) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Esta loja já está na matriz.',
            ]);
        }

        return $cliente;
    }

    /**
     * @param  list<int>  $clienteIds
     * @return array<int, list<int>>
     */
    private function mapaFrutasPorClientes(array $clienteIds): array
    {
        if ($clienteIds === []) {
            return [];
        }

        $frutasPorCliente = [];
        $vinculos = ClienteFrutaVinculo::query()
            ->whereIn('id_cliente', $clienteIds)
            ->where('ativo', true)
            ->get(['id_cliente', 'id_fruta']);

        foreach ($vinculos as $vinculo) {
            $frutasPorCliente[$vinculo->id_cliente][] = (int) $vinculo->id_fruta;
        }

        return $frutasPorCliente;
    }

    /**
     * @param  Collection<int, Cliente>  $clientes
     * @param  Collection<int, Fruta>  $frutas
     */
    private function layoutHash(Collection $clientes, Collection $frutas): string
    {
        $partes = [
            'c:'. $clientes->pluck('id')->sort()->implode(','),
            'f:'. $frutas->pluck('id')->sort()->implode(','),
        ];

        return sha1(implode('|', $partes));
    }

    public function assertFrutaVinculadaAoCliente(int $idCliente, int $idFruta): void
    {
        if ($this->clientePossuiFruta($idCliente, $idFruta)) {
            return;
        }

        throw ValidationException::withMessages([
            'id_fruta' => 'Esta fruta não está vinculada à loja. Configure em Captação → Frutas por loja.',
        ]);
    }
}
