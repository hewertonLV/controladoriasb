<?php

namespace App\Support\Captacao\Seed;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Enums\PedidoOrigem;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoLoteService;
use App\Support\TextoCadastro;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class CaptacaoBarbalhaExemploSeedService
{
    private const CARTEIRA_NOME = 'Barbalha';

    private const FATURAMENTO_NOME = 'CD BARBALHA';

    private const GALPAO_NOME = 'CD BARBALHA';

    private const PLANILHA_RELATIVA = 'planilhas/captação exemplo.xlsx';

    public function __construct(
        private readonly CaptacaoExemploPlanilhaReader $reader,
        private readonly CaptacaoLoteService $lotes,
    ) {}

    /**
     * @return array{
     *     data_referencia: string,
     *     carteira_id: int,
     *     lote_id: int,
     *     pedidos_processados: int,
     *     itens_gravados: int,
     *     lojas_vinculadas: int,
     *     avisos: list<string>,
     * }
     */
    public function executar(?string $dataReferencia = null, ?Command $output = null): array
    {
        $dataReferencia ??= Carbon::today()->toDateString();
        $caminhoPlanilha = base_path(self::PLANILHA_RELATIVA);

        $pedidosPlanilha = $this->reader->lerArquivo($caminhoPlanilha);

        $faturamento = $this->resolverUnidadePorNome(self::FATURAMENTO_NOME, galpao: false);
        $galpao = $this->resolverUnidadePorNome(self::GALPAO_NOME, galpao: true);

        $carteira = $this->garantirCarteiraBarbalha($faturamento, $galpao);
        $lote = $this->garantirLoteDoDia($dataReferencia, $carteira, $output);

        $indiceClientes = $this->montarIndiceClientes((int) $faturamento->id);
        $indiceFrutas = $this->montarIndiceFrutas();

        $avisos = [];
        $idsClientesPlanilha = [];
        $pedidosProcessados = 0;
        $itensGravados = 0;

        foreach ($pedidosPlanilha as $pedidoPlanilha) {
            $cliente = $this->resolverPorCodigoCigam($indiceClientes, $pedidoPlanilha['codigo_cliente']);
            if ($cliente === null) {
                $avisos[] = "Cliente «{$pedidoPlanilha['codigo_cliente']}» não encontrado no faturamento ".self::FATURAMENTO_NOME.'.';
                $output?->warn(end($avisos));

                continue;
            }

            $idsClientesPlanilha[] = (int) $cliente->id;

            $pedido = $this->upsertPedido(
                $lote,
                $cliente,
                $pedidoPlanilha['numero_pedido'],
                $pedidoPlanilha['itens'],
                $indiceFrutas,
                $avisos,
                $output,
                $itensGravados,
            );

            if ($pedido !== null) {
                $pedidosProcessados++;
            }
        }

        $lojasVinculadas = $this->vincularLojasNaCarteira(
            $carteira,
            (int) $faturamento->id,
            array_values(array_unique($idsClientesPlanilha)),
        );

        $output?->info(sprintf(
            'Captação Barbalha %s: lote %d, %d pedido(s), %d item(ns), %d loja(s) na carteira.',
            $dataReferencia,
            $lote->id,
            $pedidosProcessados,
            $itensGravados,
            $lojasVinculadas,
        ));

        if ($avisos !== []) {
            $output?->warn(sprintf('%d aviso(s) durante a importação.', count($avisos)));
        }

        return [
            'data_referencia' => $dataReferencia,
            'carteira_id' => (int) $carteira->id,
            'lote_id' => (int) $lote->id,
            'pedidos_processados' => $pedidosProcessados,
            'itens_gravados' => $itensGravados,
            'lojas_vinculadas' => $lojasVinculadas,
            'avisos' => $avisos,
        ];
    }

    private function garantirCarteiraBarbalha(UnidadeNegocio $faturamento, UnidadeNegocio $galpao): CaptacaoCarteira
    {
        $carteira = CaptacaoCarteira::query()
            ->where('nome', self::CARTEIRA_NOME)
            ->first();

        if ($carteira === null) {
            return CaptacaoCarteira::query()->create([
                'nome' => self::CARTEIRA_NOME,
                'id_unidade_negocio_faturamento' => $faturamento->id,
                'id_unidade_negocio_galpao' => $galpao->id,
                'ativo' => true,
            ]);
        }

        $carteira->forceFill([
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'ativo' => true,
        ])->save();

        return $carteira->refresh();
    }

    private function garantirLoteDoDia(
        string $dataReferencia,
        CaptacaoCarteira $carteira,
        ?Command $output = null,
    ): CaptacaoLote {
        $loteEmAndamento = CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_captacao_carteira', $carteira->id)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->orderByDesc('id')
            ->first();

        if ($loteEmAndamento !== null) {
            $output?->info(sprintf(
                'Reutilizando lote %d (captação em andamento) para alimentar os pedidos.',
                $loteEmAndamento->id,
            ));

            return $loteEmAndamento;
        }

        $possuiLoteNoDia = $this->lotes->possuiOutroLoteNaCarteiraData(
            $dataReferencia,
            (int) $carteira->id,
            CaptacaoLoteTipo::CaptacaoPedidos,
        );

        $lote = $this->lotes->abrirOuRecuperarLotePorCarteira(
            $dataReferencia,
            (int) $carteira->id,
            CaptacaoLoteTipo::CaptacaoPedidos,
        );

        if ($possuiLoteNoDia) {
            $output?->warn(sprintf(
                'Lote do dia não está em «captação em andamento»; criado lote %d para alimentação.',
                $lote->id,
            ));
        }

        return $lote;
    }

    /**
     * @param  list<int>  $idsClientes
     */
    private function vincularLojasNaCarteira(CaptacaoCarteira $carteira, int $faturamentoId, array $idsClientes): int
    {
        if ($idsClientes === []) {
            return 0;
        }

        $vinculados = 0;

        foreach ($idsClientes as $idCliente) {
            $cliente = Cliente::query()->find($idCliente);
            if ($cliente === null) {
                continue;
            }

            $cliente->forceFill([
                'id_captacao_carteira' => $carteira->id,
                'id_unidade_negocio' => $faturamentoId,
            ])->save();

            $vinculados++;
        }

        return $vinculados;
    }

    /**
     * @param  array<string, Cliente>  $indice
     */
    private function resolverPorCodigoCigam(array $indice, string $codigo): ?Cliente
    {
        foreach ($this->chavesCodigoCigam($codigo) as $chave) {
            if (isset($indice[$chave])) {
                return $indice[$chave];
            }
        }

        return null;
    }

    /**
     * @param  array<string, Fruta>  $indice
     */
    private function resolverFrutaPorCodigo(array $indice, string $codigo): ?Fruta
    {
        foreach ($this->chavesCodigoCigam($codigo) as $chave) {
            if (isset($indice[$chave])) {
                return $indice[$chave];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function chavesCodigoCigam(string $codigo): array
    {
        $codigo = trim($codigo);
        $normalizado = TextoCadastro::normalizarIdCigam($codigo);
        $semZeros = ltrim($normalizado, '0');

        return array_values(array_unique(array_filter([
            $codigo,
            $normalizado,
            $semZeros !== '' ? $semZeros : null,
            mb_strtoupper($codigo, 'UTF-8'),
        ])));
    }

    /**
     * @return array<string, Cliente>
     */
    private function montarIndiceClientes(int $faturamentoId): array
    {
        $indice = [];

        $clientes = Cliente::query()
            ->where('id_unidade_negocio', $faturamentoId)
            ->get(['id', 'id_cigam', 'razao_social', 'fantasia']);

        foreach ($clientes as $cliente) {
            foreach ($this->chavesCodigoCigam((string) $cliente->id_cigam) as $chave) {
                $indice[$chave] = $cliente;
            }
        }

        return $indice;
    }

    /**
     * @return array<string, Fruta>
     */
    private function montarIndiceFrutas(): array
    {
        $indice = [];

        $frutas = Fruta::query()->get(['id', 'id_cigam', 'nome']);

        foreach ($frutas as $fruta) {
            foreach ($this->chavesCodigoCigam((string) $fruta->id_cigam) as $chave) {
                $indice[$chave] = $fruta;
            }
        }

        return $indice;
    }

    /**
     * @param  list<array{codigo_fruta: string, quantidade: string, preco_venda: string|null, linha: int}>  $itens
     * @param  array<string, Fruta>  $indiceFrutas
     * @param  list<string>  $avisos
     */
    private function upsertPedido(
        CaptacaoLote $lote,
        Cliente $cliente,
        ?string $numeroPedido,
        array $itens,
        array $indiceFrutas,
        array &$avisos,
        ?Command $output,
        int &$itensGravados,
    ): ?Pedido {
        if ($itens === []) {
            return null;
        }

        $pedido = Pedido::query()->firstOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_cliente' => $cliente->id,
            ],
            [
                'origem' => PedidoOrigem::Web,
                'captacao_concluida' => false,
            ],
        );

        if ($pedido->captacao_concluida) {
            $pedido->forceFill(['captacao_concluida' => false])->save();
        }

        if ($numeroPedido !== null && $numeroPedido !== '') {
            $pedido->forceFill(['numero_pedido' => $numeroPedido])->save();
        }

        $idFrutasPedido = [];

        foreach ($itens as $item) {
            $fruta = $this->resolverFrutaPorCodigo($indiceFrutas, $item['codigo_fruta']);
            if ($fruta === null) {
                $msg = "Fruta «{$item['codigo_fruta']}» não encontrada (linha {$item['linha']}).";
                $avisos[] = $msg;
                $output?->warn($msg);

                continue;
            }

            $idFrutasPedido[] = (int) $fruta->id;

            ClienteFrutaVinculo::query()->updateOrCreate(
                [
                    'id_cliente' => $cliente->id,
                    'id_fruta' => $fruta->id,
                ],
                ['ativo' => true],
            );

            PedidoItem::query()->updateOrCreate(
                [
                    'id_pedido' => $pedido->id,
                    'id_fruta' => $fruta->id,
                ],
                [
                    'quantidade' => $item['quantidade'],
                    'preco_venda' => $item['preco_venda'],
                    'custo_referencia' => null,
                    'version' => 1,
                ],
            );

            $itensGravados++;
        }

        if ($idFrutasPedido === []) {
            return null;
        }

        return $pedido->refresh();
    }

    private function resolverUnidadePorNome(string $nome, bool $galpao): UnidadeNegocio
    {
        $query = UnidadeNegocio::query()
            ->whereRaw('UPPER(TRIM(nome)) = ?', [mb_strtoupper(trim($nome), 'UTF-8')]);

        if ($galpao) {
            $query->where('is_galpao_operacional', true);
        }

        $unidade = $query->first();

        if ($unidade === null) {
            throw new \RuntimeException(
                'Unidade de negócio «'.$nome.'»'
                .($galpao ? ' (galpão operacional)' : '')
                .' não encontrada. Cadastre/importe antes de rodar o seeder.',
            );
        }

        return $unidade;
    }
}
