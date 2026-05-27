<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\Alertas\FrutasHabituaisAusentesQuery;
use App\Services\Captacao\Alertas\LojasSemPedidoDiaSemanaQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertasComerciaisController extends Controller
{
    public function index(Request $request): View
    {
        $dataReferencia = $request->string('data_referencia', now()->toDateString())->toString();
        $idFaturamento = $request->integer('id_unidade_negocio_faturamento');
        $idGalpao = $request->filled('id_unidade_negocio_galpao')
            ? $request->integer('id_unidade_negocio_galpao')
            : null;

        $lojasSemPedido = collect();
        $frutasFaltantes = collect();

        if ($idFaturamento > 0) {
            $lojasSemPedido = app(LojasSemPedidoDiaSemanaQuery::class)->executar(
                $dataReferencia,
                $idFaturamento,
                $idGalpao,
            );
            $frutasFaltantes = app(FrutasHabituaisAusentesQuery::class)->executar(
                $dataReferencia,
                $idFaturamento,
                $idGalpao,
            );
        }

        return view('admin.captacao.alertas.index', [
            'dataReferencia' => $dataReferencia,
            'idFaturamento' => $idFaturamento,
            'idGalpao' => $idGalpao,
            'lojasSemPedido' => $lojasSemPedido,
            'frutasFaltantes' => $frutasFaltantes,
            'faturamentos' => UnidadeNegocio::query()
                ->where('emite_nota_fiscal', true)
                ->where('is_hub', false)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'galpoes' => UnidadeNegocio::query()
                ->where('is_galpao_operacional', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }
}
