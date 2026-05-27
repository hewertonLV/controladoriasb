<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Services\Captacao\Alertas\LojasPendentesCaptacaoQuery;
use App\Services\Captacao\CaptacaoCarteiraService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoConsultaController extends Controller
{
    public function lojasPendentes(Request $request): View
    {
        $dataReferencia = $request->string('data_referencia', now()->toDateString())->toString();

        $idCarteira = $request->filled('id_captacao_carteira')
            && $request->integer('id_captacao_carteira') > 0
            ? $request->integer('id_captacao_carteira')
            : null;

        $resultado = app(LojasPendentesCaptacaoQuery::class)->executar(
            $dataReferencia,
            $request->user(),
            $idCarteira,
        );

        $carteiras = app(CaptacaoCarteiraService::class)
            ->carteirasAcessiveisParaUsuario($request->user());

        return view('admin.captacao.consulta.lojas-pendentes', [
            'dataReferencia' => $resultado['data_referencia'],
            'diaSemanaLabel' => $resultado['dia_semana_label'],
            'idCarteira' => $idCarteira ?? 0,
            'linhas' => $resultado['linhas'],
            'totais' => $resultado['totais'],
            'carteiras' => $carteiras,
        ]);
    }

    /** @deprecated Use lojasPendentes — mantido para compatibilidade de rota antiga */
    public function clientesSemPedido(Request $request): View
    {
        return $this->lojasPendentes($request);
    }
}
