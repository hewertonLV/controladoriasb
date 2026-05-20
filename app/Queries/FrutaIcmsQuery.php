<?php

namespace App\Queries;

use App\Enums\FrutaIcmsOperacao;
use App\Models\FrutaIcms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
     * @param  Builder<FrutaIcms>  $query
     * @param  array{search?: string}  $filtros
     * @return Builder<FrutaIcms>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $search = trim((string) ($filtros['search'] ?? ''));

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
            ->join('frutas', 'frutas.id', '=', 'fruta_icms.fruta_id')
            ->join('estados', 'estados.id', '=', 'fruta_icms.id_estado')
            ->orderBy('frutas.nome')
            ->orderBy('estados.nome')
            ->select('fruta_icms.*');
    }

    /**
     * @return Builder<FrutaIcms>
     */
    public function listagemBase(): Builder
    {
        return FrutaIcms::query()
            ->where('fruta_icms.operacao', FrutaIcmsOperacao::ENTRADA)
            ->with(['fruta', 'estado']);
    }
}
