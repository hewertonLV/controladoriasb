<?php

namespace App\Support\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;

final class CaptacaoPedidoPorLojaSaidaFisicaService
{
    public function __construct(
        private readonly SaidaEstoqueFisicoCaptacaoService $saidaCaptacao,
    ) {}

    /**
     * Galpão do faturamento do lote + cada HUB ativo (saída física na captação).
     *
     * @return list<array{id: int, label: string, label_curto: string, grupo: string}>
     */
    public function opcoesParaLote(CaptacaoLote $lote): array
    {
        $lote->loadMissing(['unidadeGalpao:id,nome,id_cigam', 'unidadeFaturamento:id,nome']);

        $opcoes = [];
        $galpao = $lote->unidadeGalpao;

        if ($galpao !== null) {
            $faturamentoNome = $lote->unidadeFaturamento?->nome;
            $rotulo = 'Galpão — '.$galpao->nome.' ('.$galpao->id_cigam.')';
            if ($faturamentoNome) {
                $rotulo .= ' · fatur. '.$faturamentoNome;
            }

            $opcoes[] = [
                'id' => (int) $galpao->id,
                'label' => $rotulo,
                'label_curto' => $galpao->nome,
                'grupo' => 'Galpão do faturamento',
            ];
        }

        foreach ($this->hubsAtivos() as $hub) {
            $prefixo = $hub->is_galpao_operacional ? 'Galpão/HUB' : 'HUB';
            $opcoes[] = [
                'id' => (int) $hub->id,
                'label' => $prefixo.' — '.$hub->nome.' ('.$hub->id_cigam.')',
                'label_curto' => $hub->nome,
                'grupo' => 'Unidades HUB',
            ];
        }

        return $opcoes;
    }

    /**
     * @return list<int>
     */
    public function idsUnidadesPermitidas(CaptacaoLote $lote): array
    {
        return array_values(array_unique(array_map(
            static fn (array $opcao): int => $opcao['id'],
            $this->opcoesParaLote($lote),
        )));
    }

    public function unidadePermitida(CaptacaoLote $lote, int $idUnidade): bool
    {
        return in_array($idUnidade, $this->idsUnidadesPermitidas($lote), true);
    }

    public function idSaidaEfetivaParaExibicao(Pedido $pedido, CaptacaoLote $lote, Cliente $cliente): int
    {
        if ($pedido->id_unidade_negocio_saida_venda !== null) {
            return (int) $pedido->id_unidade_negocio_saida_venda;
        }

        return $this->saidaCaptacao->idSaidaPadraoParaCliente($cliente, $lote);
    }

    public function labelUnidadePorId(CaptacaoLote $lote, int $idUnidade): ?string
    {
        foreach ($this->opcoesParaLote($lote) as $opcao) {
            if ($opcao['id'] === $idUnidade) {
                return $opcao['label'];
            }
        }

        return UnidadeNegocio::query()->whereKey($idUnidade)->value('nome');
    }

    public function labelCurtoUnidadePorId(CaptacaoLote $lote, int $idUnidade): string
    {
        foreach ($this->opcoesParaLote($lote) as $opcao) {
            if ($opcao['id'] === $idUnidade) {
                return $opcao['label_curto'];
            }
        }

        return (string) (UnidadeNegocio::query()->whereKey($idUnidade)->value('nome') ?? '—');
    }

    /**
     * @return Collection<int, UnidadeNegocio>
     */
    private function hubsAtivos(): Collection
    {
        return UnidadeNegocio::query()
            ->ativas()
            ->where('is_hub', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam', 'is_galpao_operacional']);
    }
}
