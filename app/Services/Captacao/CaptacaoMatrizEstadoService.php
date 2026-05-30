<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;

final class CaptacaoMatrizEstadoService
{
    public function __construct(
        private readonly ClienteFrutaVinculoService $vinculos,
        private readonly CaptacaoMatrizRotasService $matrizRotas,
        private readonly ConcluirCaptacaoLoteService $concluirCaptacaoLote,
    ) {}

    /**
     * @return array{
     *     lote_id: int,
     *     version: int,
     *     updated_at: string,
     *     clientes: list<array{id: int, nome: string}>,
     *     frutas: list<array{id: int, nome: string}>,
     *     celulas: array<string, array{quantidade: string, preco_venda: string|null, version: int}>,
     *     pedidos: array<string, array{
     *         captacao_concluida: bool,
     *         numero_pedido: string|null,
     *         id_captacao_rota: int|null,
     *         ordem_carregamento: int|null,
     *         id_unidade_negocio_saida_venda: int|null,
     *     }>,
     *     linhas_rotas: list<array<string, mixed>>,
     *     grupos_ordem_carregamento: list<array<string, mixed>>,
     *     rotas: list<array{id: int, nome: string, nome_motorista: string|null, id_veiculo: int|null}>,
     *     veiculos: list<array{id: int, id_sbs: int, nome: string}>,
     *     carteira: array{id: int|null, nome: string|null},
     *     frutas_por_cliente: array<int, list<int>>,
     *     layout_hash: string,
     *     rotas_pendentes_conclusao_captacao: list<string>,
     *     todas_rotas_do_lote_concluidas: bool,
     *     conclusao_captacao_lote: array{pode: bool, pendencias: list<string>, lote_status: string},
     * }
     */
    public function snapshot(CaptacaoLote $lote): array
    {
        $matriz = $this->vinculos->dadosMatriz($lote);

        $lote->load([
            'pedidos.cliente:id,razao_social,fantasia,id_unidade_negocio_saida_fisico_padrao',
            'carteira:id,nome',
        ]);
        $clientes = $matriz['clientes'];
        $frutas = $matriz['frutas'];

        $celulas = [];
        $pedidos = [];
        $versionSum = 0;

        $pedidosPorCliente = $lote->pedidos->keyBy('id_cliente');
        $saidaFisicaLoja = app(CaptacaoPedidoPorLojaSaidaFisicaService::class);
        $saidaFisicaRomaneio = app(SaidaEstoqueFisicoCaptacaoService::class);
        $usaSaidaPorLoja = $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento;

        foreach ($lote->pedidos as $pedido) {
            $cliente = $pedido->cliente;
            $idSaidaExibicao = $usaSaidaPorLoja && $cliente !== null
                ? $saidaFisicaLoja->idSaidaEfetivaParaExibicao($pedido, $lote, $cliente)
                : $saidaFisicaRomaneio->idSaidaEfetiva($pedido, $lote);

            $pedidos[(string) $pedido->id_cliente] = [
                'captacao_concluida' => (bool) $pedido->captacao_concluida,
                'numero_pedido' => $pedido->numero_pedido,
                'id_captacao_rota' => $pedido->id_captacao_rota,
                'ordem_carregamento' => $pedido->ordem_carregamento !== null
                    ? (int) $pedido->ordem_carregamento
                    : null,
                'id_unidade_negocio_saida_venda' => $idSaidaExibicao,
            ];
            $versionSum += (int) $pedido->updated_at?->timestamp;
            $versionSum += $pedido->captacao_concluida ? 1009 : 0;
            $versionSum += strlen($pedido->numero_pedido ?? '');
            $versionSum += (int) ($pedido->id_captacao_rota ?? 0);
            $versionSum += (int) ($pedido->ordem_carregamento ?? 0);
            $versionSum += $idSaidaExibicao;

            foreach ($pedido->itens as $item) {
                $key = $pedido->id_cliente.'_'.$item->id_fruta;
                $celulas[$key] = [
                    'quantidade' => (string) $item->quantidade,
                    'preco_venda' => $item->preco_venda !== null ? (string) $item->preco_venda : null,
                    'version' => (int) $item->version,
                ];
                $versionSum += (int) $item->version;
            }
        }

        $linhasRotas = $this->matrizRotas->gruposPorLoja(
            $lote,
            $clientes,
            $matriz['frutasPorCliente'],
            $pedidosPorCliente,
            $frutas,
        );

        $rotas = $this->matrizRotas->rotasDaCarteira($lote);
        $gruposOrdemCarregamento = $this->matrizRotas->gruposOrdemCarregamento($linhasRotas, $rotas, $lote);
        $veiculos = $this->matrizRotas->veiculosDisponiveis();

        $configsLoteRota = $this->matrizRotas->configPorRotaNoLote($lote);
        foreach ($configsLoteRota as $config) {
            $versionSum += strlen($config->nome_motorista ?? '');
            $versionSum += (int) ($config->id_veiculo ?? 0);
            $versionSum += $config->concluida ? 2027 : 0;
            $versionSum += (int) $config->updated_at?->timestamp;
        }

        $conclusaoCaptacaoLote = $this->concluirCaptacaoLote->pendenciasParaConcluir($lote);
        $versionSum += $conclusaoCaptacaoLote['pode'] ? 4091 : 0;
        foreach ($conclusaoCaptacaoLote['pendencias'] as $pendencia) {
            $versionSum += strlen($pendencia);
        }
        $versionSum += strlen($lote->status->value);

        return [
            'lote_id' => $lote->id,
            'version' => $versionSum + (int) $lote->updated_at?->timestamp,
            'updated_at' => $lote->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'layout_hash' => $matriz['layout_hash'],
            'clientes' => $clientes->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->fantasia ?: $c->razao_social,
            ])->values()->all(),
            'frutas' => $frutas->map(fn ($f) => [
                'id' => $f->id,
                'nome' => $f->nome,
            ])->values()->all(),
            'frutas_por_cliente' => $matriz['frutasPorCliente'],
            'celulas' => $celulas,
            'pedidos' => $pedidos,
            'linhas_rotas' => $linhasRotas,
            'grupos_ordem_carregamento' => $gruposOrdemCarregamento,
            'rotas' => $rotas->map(fn ($r) => [
                'id' => $r->id,
                'nome' => $r->nome,
                'nome_motorista' => $r->nome_motorista,
                'id_veiculo' => $r->id_veiculo,
                'concluida' => (bool) ($r->concluida ?? false),
            ])->values()->all(),
            'veiculos' => $veiculos->map(fn ($v) => [
                'id' => $v->id,
                'id_sbs' => $v->id_sbs,
                'nome' => $v->nome,
            ])->values()->all(),
            'carteira' => [
                'id' => $lote->id_captacao_carteira,
                'nome' => $lote->carteira?->nome,
            ],
            'rotas_pendentes_conclusao_captacao' => $this->matrizRotas->nomesRotasComPedidoNaoConcluidasNoLote($lote),
            'todas_rotas_do_lote_concluidas' => $this->matrizRotas->todasRotasComPedidoEstaoConcluidasNoLote($lote),
            'conclusao_captacao_lote' => [
                'pode' => $conclusaoCaptacaoLote['pode'],
                'pendencias' => $conclusaoCaptacaoLote['pendencias'],
                'lote_status' => $lote->status->value,
            ],
        ];
    }

    /**
     * Soma de quantidades por coluna (fruta) na matriz.
     *
     * @param  iterable<\App\Models\Fruta>  $frutas
     * @param  iterable<\App\Models\Cliente>  $clientes
     * @return array<int, float>
     */
    public function totaisPorFruta(CaptacaoLote $lote, iterable $frutas, iterable $clientes): array
    {
        $lote->loadMissing(['pedidos.itens']);

        $totais = [];

        foreach ($frutas as $fruta) {
            $total = 0.0;

            foreach ($clientes as $cliente) {
                $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
                $item = $pedido?->itens->firstWhere('id_fruta', $fruta->id);
                $total += (float) ($item?->quantidade ?? 0);
            }

            $totais[$fruta->id] = $total;
        }

        return $totais;
    }
}
