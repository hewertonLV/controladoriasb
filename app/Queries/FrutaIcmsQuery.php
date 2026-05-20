<?php

namespace App\Queries;

use App\Models\FrutaIcmsAliquota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FrutaIcmsQuery
{
    /**
     * @return array{search: string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    /**
     * @return Collection<int, object{fruta_id: int, id_estado: int, fruta: mixed, estado: mixed}>
     */
    public function listagemAgrupada(array $filtros = []): Collection
    {
        $search = trim((string) ($filtros['search'] ?? ''));

        $query = FrutaIcmsAliquota::query()
            ->select('fruta_id', 'id_estado')
            ->distinct()
            ->with(['fruta', 'estado']);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('fruta', function (Builder $fq) use ($search): void {
                    $fq->where('nome', 'like', '%'.$search.'%')
                        ->orWhere('id_cigam', 'like', '%'.$search.'%');
                })->orWhereHas('estado', function (Builder $eq) use ($search): void {
                    $eq->where('nome', 'like', '%'.$search.'%')
                        ->orWhere('abreviacao', 'like', '%'.$search.'%');
                });
            });
        }

        return $query
            ->get()
            ->unique(fn (FrutaIcmsAliquota $a) => $a->fruta_id.'-'.$a->id_estado)
            ->sortBy(fn (FrutaIcmsAliquota $a) => ($a->fruta?->nome ?? '').'|'.($a->estado?->nome ?? ''))
            ->values();
    }
}
