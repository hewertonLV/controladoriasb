<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoCarteira;
use App\Services\Captacao\Alertas\ClientesSemPedidoCarteiraQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoConsultaController extends Controller
{
    public function clientesSemPedido(Request $request): View
    {
        $dataReferencia = $request->string('data_referencia', now()->toDateString())->toString();
        $idCarteira = $request->integer('id_captacao_carteira');

        $clientesSemPedido = collect();

        if ($idCarteira > 0) {
            $clientesSemPedido = app(ClientesSemPedidoCarteiraQuery::class)->executar(
                $dataReferencia,
                $idCarteira,
            );
        }

        return view('admin.captacao.consulta.sem-pedido', [
            'dataReferencia' => $dataReferencia,
            'idCarteira' => $idCarteira,
            'clientesSemPedido' => $clientesSemPedido,
            'carteiras' => CaptacaoCarteira::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }
}
