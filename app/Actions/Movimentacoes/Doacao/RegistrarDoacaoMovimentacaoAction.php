<?php

namespace App\Actions\Movimentacoes\Doacao;

use App\Http\Requests\Admin\Movimentacoes\StoreDoacaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DoacaoMovimentacaoService;
use App\Services\Movimentacoes\MovimentacaoService;

final class RegistrarDoacaoMovimentacaoAction
{
    public function __construct(
        private readonly DoacaoMovimentacaoService $doacao,
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function __invoke(StoreDoacaoMovimentacaoRequest $request): Movimentacao
    {
        $dados = $request->validated();
        $this->doacao->assertPodeRegistrar($dados);

        $modelo = Movimentacao::make($dados);
        $modelo->loadMissing('fruta');
        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($modelo);

        return $modelo;
    }
}
