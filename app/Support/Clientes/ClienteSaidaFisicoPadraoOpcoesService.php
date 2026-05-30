<?php

namespace App\Support\Clientes;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;

final class ClienteSaidaFisicoPadraoOpcoesService
{
    /**
     * @return list<array{id: int, label: string, grupo: string}>
     */
    public function opcoesParaUnidadeFaturamento(?int $idUnidadeFaturamento): array
    {
        $opcoes = [];
        $idsIncluidos = [];

        if ($idUnidadeFaturamento !== null && $idUnidadeFaturamento > 0) {
            $faturamentoNome = UnidadeNegocio::query()
                ->whereKey($idUnidadeFaturamento)
                ->value('nome');

            foreach ($this->galpoesDaUnidadeFaturamento($idUnidadeFaturamento) as $galpao) {
                $opcoes[] = [
                    'id' => $galpao->id,
                    'label' => $this->rotuloGalpao($galpao, is_string($faturamentoNome) ? $faturamentoNome : null),
                    'grupo' => 'Galpão do faturamento',
                ];
                $idsIncluidos[$galpao->id] = true;
            }
        }

        foreach ($this->galpoesOperacionaisRede() as $galpao) {
            if (isset($idsIncluidos[$galpao->id])) {
                continue;
            }

            $opcoes[] = [
                'id' => $galpao->id,
                'label' => $this->rotuloGalpao($galpao),
                'grupo' => 'Galpões da rede (HUB → galpão)',
            ];
            $idsIncluidos[$galpao->id] = true;
        }

        foreach ($this->hubsAtivos() as $hub) {
            if (isset($idsIncluidos[$hub->id])) {
                continue;
            }

            $opcoes[] = [
                'id' => $hub->id,
                'label' => $this->rotuloHub($hub),
                'grupo' => 'HUB',
            ];
            $idsIncluidos[$hub->id] = true;
        }

        return $opcoes;
    }

    /**
     * @return array<string, list<array{id: int, label: string, grupo: string}>>
     */
    public function opcoesAgrupadasParaUnidadeFaturamento(?int $idUnidadeFaturamento): array
    {
        $agrupadas = [];

        foreach ($this->opcoesParaUnidadeFaturamento($idUnidadeFaturamento) as $opcao) {
            $agrupadas[$opcao['grupo']][] = $opcao;
        }

        return $agrupadas;
    }

    /**
     * @return array<int, list<array{id: int, label: string, grupo: string}>>
     */
    public function mapaJsonParaFormulario(): array
    {
        $faturamentos = UnidadeNegocio::query()
            ->where('emite_nota_fiscal', true)
            ->where('is_hub', false)
            ->orderBy('nome')
            ->pluck('id');

        $mapa = [];

        foreach ($faturamentos as $idFaturamento) {
            $mapa[(int) $idFaturamento] = $this->opcoesParaUnidadeFaturamento((int) $idFaturamento);
        }

        return $mapa;
    }

    public function opcaoPermitida(?int $idUnidadeFaturamento, int $idUnidadeSaida): bool
    {
        if ($idUnidadeFaturamento === null || $idUnidadeFaturamento < 1) {
            return false;
        }

        foreach ($this->opcoesParaUnidadeFaturamento($idUnidadeFaturamento) as $opcao) {
            if ($opcao['id'] === $idUnidadeSaida) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, UnidadeNegocio>
     */
    private function galpoesDaUnidadeFaturamento(int $idUnidadeFaturamento): Collection
    {
        return CaptacaoCarteira::query()
            ->where('ativo', true)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->with(['unidadeGalpao:id,nome,id_cigam,is_galpao_operacional'])
            ->orderBy('nome')
            ->get()
            ->map(fn (CaptacaoCarteira $carteira) => $carteira->unidadeGalpao)
            ->filter(fn (?UnidadeNegocio $galpao): bool => $galpao !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Galpões operacionais usados em carteiras de captação (destino típico HUB → galpão).
     *
     * @return Collection<int, UnidadeNegocio>
     */
    private function galpoesOperacionaisRede(): Collection
    {
        return CaptacaoCarteira::query()
            ->where('ativo', true)
            ->with(['unidadeGalpao:id,nome,id_cigam,is_galpao_operacional'])
            ->orderBy('nome')
            ->get()
            ->map(fn (CaptacaoCarteira $carteira) => $carteira->unidadeGalpao)
            ->filter(fn (?UnidadeNegocio $galpao): bool => $galpao !== null
                && $galpao->is_galpao_operacional === true)
            ->unique('id')
            ->sortBy('nome')
            ->values();
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
            ->get(['id', 'nome', 'id_cigam']);
    }

    private function rotuloGalpao(UnidadeNegocio $galpao, ?string $nomeFaturamento = null): string
    {
        $nome = $galpao->nome;
        $cigam = $galpao->id_cigam;
        $rotulo = "Galpão — {$nome} ({$cigam})";

        if ($nomeFaturamento !== null && $nomeFaturamento !== '') {
            $rotulo .= " · fatur. {$nomeFaturamento}";
        }

        return $rotulo;
    }

    private function rotuloHub(UnidadeNegocio $hub): string
    {
        return "HUB — {$hub->nome} ({$hub->id_cigam})";
    }
}
