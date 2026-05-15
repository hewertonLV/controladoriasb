<?php

namespace App\Actions\Movimentacoes\Devolucao;

use App\Http\Requests\Admin\Movimentacoes\StoreDevolucaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DevolucaoMovimentacaoService;
use App\Services\Movimentacoes\MovimentacaoService;

final class RegistrarDevolucaoMovimentacaoAction
{
    public function __construct(
        private readonly DevolucaoMovimentacaoService $devolucao,
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function __invoke(StoreDevolucaoMovimentacaoRequest $request): Movimentacao
    {
        $dados = $request->validated();
        $this->devolucao->assertPodeRegistrar($dados);

        $modelo = Movimentacao::make($dados);
        $modelo->loadMissing('fruta');
        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($modelo);

        return $modelo;
    }
}
