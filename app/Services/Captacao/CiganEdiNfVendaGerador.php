<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Support\Captacao\Cigan\CiganEdiLinha;
use Illuminate\Validation\ValidationException;

/**
 * TXT EDI NF Cigam — vendas do lote (origem: faturamento → destino: loja).
 *
 * @see docs/decisions/ADR-0126-arquivo-cigan-edi-vendas-faturamento.md
 */
final class CiganEdiNfVendaGerador
{
    private const COMPRIMENTO_LINHA_N = 688;

    private const COMPRIMENTO_LINHA_I = 719;

    public function __construct(
        private readonly CiganEdiNfTransferenciaGerador $campos,
    ) {}

    public function gerar(CaptacaoLote $lote): string
    {
        $lote = CaptacaoLote::query()->findOrFail($lote->id);
        $lote->loadMissing([
            'unidadeFaturamento',
            'unidadeGalpao',
            'pedidos.cliente.unidadeNegocio.estado',
            'pedidos.itens.fruta',
        ]);

        $faturamento = $lote->unidadeFaturamento;
        if ($faturamento === null) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_faturamento' => 'Lote sem unidade de faturamento.',
            ]);
        }

        $codigoUnidadeOrigem = $this->campos->codigoUnidadeNegocioCigam(
            (string) $faturamento->id_cigam,
            'unidade de faturamento',
        );
        $codigoCentroArmazenagem = $this->campos->codigoCentroArmazenagemCigam($faturamento);

        $idCigamFaturamento = trim((string) $faturamento->id_cigam);
        if ($idCigamFaturamento === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam da unidade de faturamento «'.$faturamento->nome.'» antes de gerar o arquivo de vendas.',
            ]);
        }

        $serieNumero = $this->campos->serieENumeroNotaFiscalCigam($idCigamFaturamento);

        $dataEmissao = $this->campos->dataEmissaoCigam();
        $dataEntrada = $this->campos->dataEntradaCigam();

        $linhas = [];

        foreach ($lote->pedidos as $pedido) {
            $bloco = $this->gerarBlocoLoja(
                $pedido,
                $dataEmissao,
                $dataEntrada,
                $serieNumero,
                $codigoUnidadeOrigem,
                $codigoCentroArmazenagem,
            );

            if ($bloco !== null) {
                array_push($linhas, ...$bloco);
            }
        }

        if ($linhas === []) {
            throw ValidationException::withMessages([
                'pedidos' => 'Não há itens com quantidade para gerar o arquivo de vendas Cigam.',
            ]);
        }

        return implode("\n", $linhas)."\n";
    }

    public function gerarPorDemanda(CaptacaoLoteMovimentacao $demanda): string
    {
        $demanda->loadMissing([
            'lote.unidadeFaturamento',
            'lote.unidadeGalpao',
            'linhas.fruta',
            'linhas.pedido.cliente.unidadeNegocio.estado',
        ]);

        $lote = $demanda->lote;
        if ($lote === null) {
            throw ValidationException::withMessages(['demanda' => 'Demanda sem lote vinculado.']);
        }

        $faturamento = $lote->unidadeFaturamento;
        if ($faturamento === null) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_faturamento' => 'Lote sem unidade de faturamento.',
            ]);
        }

        $codigoUnidadeOrigem = $this->campos->codigoUnidadeNegocioCigam(
            (string) $faturamento->id_cigam,
            'unidade de faturamento',
        );
        $codigoCentroArmazenagem = $this->campos->codigoCentroArmazenagemCigam($faturamento);

        $idCigamFaturamento = trim((string) $faturamento->id_cigam);
        if ($idCigamFaturamento === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam da unidade de faturamento «'.$faturamento->nome.'» antes de gerar o arquivo de vendas.',
            ]);
        }

        $serieNumero = $this->campos->serieENumeroNotaFiscalCigam($idCigamFaturamento);
        $dataEmissao = $this->campos->dataEmissaoCigam();
        $dataEntrada = $this->campos->dataEntradaCigam();

        $idsPedido = $demanda->linhas
            ->pluck('id_pedido')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $pedidos = Pedido::query()
            ->with(['cliente.unidadeNegocio.estado'])
            ->whereIn('id', $idsPedido)
            ->orderBy('ordem_carregamento')
            ->orderBy('id')
            ->get();

        $linhas = [];

        foreach ($pedidos as $pedido) {
            $linhasDemanda = $demanda->linhas->where('id_pedido', $pedido->id)->values();
            $bloco = $this->gerarBlocoLojaComLinhasDemanda(
                $pedido,
                $linhasDemanda,
                $dataEmissao,
                $dataEntrada,
                $serieNumero,
                $codigoUnidadeOrigem,
                $codigoCentroArmazenagem,
            );

            if ($bloco !== null) {
                array_push($linhas, ...$bloco);
            }
        }

        if ($linhas === []) {
            throw ValidationException::withMessages([
                'pedidos' => 'Não há itens com quantidade para gerar o arquivo de vendas Cigam.',
            ]);
        }

        return implode("\n", $linhas)."\n";
    }

    public function paraIso88591(string $conteudoUtf8): string
    {
        return $this->campos->paraIso88591($conteudoUtf8);
    }

    /**
     * @param  array{serie: string, numero: string}  $serieNumero
     * @return list<string>|null Blocos N + linhas I
     */
    private function gerarBlocoLoja(
        Pedido $pedido,
        string $dataEmissao,
        string $dataEntrada,
        array $serieNumero,
        string $codigoUnidadeOrigem,
        string $codigoCentroArmazenagem,
    ): ?array {
        $itens = $pedido->itens
            ->filter(static fn ($item) => (float) $item->quantidade > 0)
            ->values();

        if ($itens->isEmpty()) {
            return null;
        }

        $cliente = $pedido->cliente;
        if ($cliente === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Pedido sem loja vinculada.',
            ]);
        }

        $this->validarClienteLoja($cliente);

        $codigoCliente = $this->codigoClienteLojaCigam($cliente);
        $nomeCliente = $this->texto($cliente->fantasia ?: $cliente->razao_social ?: '', 60);
        $uf = $this->campos->ufClienteCigam($cliente);
        $cnpj = $this->documentoCigam((string) $cliente->cnpj_cpf);
        $tipoPessoa = strlen(preg_replace('/\D/', '', $cnpj) ?? '') === 11 ? 'F' : 'J';

        $linhasI = [];

        foreach ($itens as $item) {
            $fruta = $item->fruta;
            $idCigam = trim((string) ($fruta?->id_cigam ?? ''));
            if ($idCigam === '') {
                throw ValidationException::withMessages([
                    'id_cigam' => 'Cadastre o ID Cigam da fruta «'.($fruta?->nome ?? '—').'» antes de gerar o arquivo de vendas.',
                ]);
            }

            $precoVenda = $item->preco_venda;
            if ($precoVenda === null || (float) $precoVenda <= 0) {
                throw ValidationException::withMessages([
                    'preco_venda' => 'Informe o preço da fruta «'.($fruta->nome ?? '—').'» na captação antes de gerar o arquivo de vendas Cigam.',
                ]);
            }

            $linhasI[] = $this->montarRegistroItem(
                codigoMaterial: $this->campos->codigoMaterialCigam($idCigam),
                descricao: $this->texto((string) ($fruta->nome ?? ''), 200),
                quantidadeUm: (float) $item->quantidade,
                precoUnitario: (float) $precoVenda,
                codigoUnidadeNegocio: $codigoUnidadeOrigem,
            );
        }

        $linhaN = $this->montarRegistroNota(
            dataEmissao: $dataEmissao,
            dataEntrada: $dataEntrada,
            serieNotaFiscal: $serieNumero['serie'],
            numeroNotaFiscal: $serieNumero['numero'],
            codigoClienteCobranca: $codigoCliente,
            nomeDestino: $nomeCliente,
            uf: $uf,
            cnpj: $cnpj,
            tipoPessoa: $tipoPessoa,
            numeroDivisao: $this->campos->numeroDivisaoCigam($cliente),
            unidadeNegocio: $codigoUnidadeOrigem,
            centroArmazenagem: $codigoCentroArmazenagem,
            quantidadeItens: count($linhasI),
        );

        return array_merge([$linhaN], $linhasI);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Captacao\CaptacaoLoteMovimentacaoLinha>  $linhasDemanda
     * @param  array{serie: string, numero: string}  $serieNumero
     * @return list<string>|null
     */
    private function gerarBlocoLojaComLinhasDemanda(
        Pedido $pedido,
        $linhasDemanda,
        string $dataEmissao,
        string $dataEntrada,
        array $serieNumero,
        string $codigoUnidadeOrigem,
        string $codigoCentroArmazenagem,
    ): ?array {
        if ($linhasDemanda->isEmpty()) {
            return null;
        }

        $cliente = $pedido->cliente;
        if ($cliente === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Pedido sem loja vinculada.',
            ]);
        }

        $this->validarClienteLoja($cliente);

        $codigoCliente = $this->codigoClienteLojaCigam($cliente);
        $nomeCliente = $this->texto($cliente->fantasia ?: $cliente->razao_social ?: '', 60);
        $uf = $this->campos->ufClienteCigam($cliente);
        $cnpj = $this->documentoCigam((string) $cliente->cnpj_cpf);
        $tipoPessoa = strlen(preg_replace('/\D/', '', $cnpj) ?? '') === 11 ? 'F' : 'J';

        $linhasI = [];

        foreach ($linhasDemanda as $linha) {
            $fruta = $linha->fruta;
            $qtdUm = (float) $linha->qtd_um;
            if ($qtdUm <= 0) {
                continue;
            }

            $idCigam = trim((string) ($fruta?->id_cigam ?? ''));
            if ($idCigam === '') {
                throw ValidationException::withMessages([
                    'id_cigam' => 'Cadastre o ID Cigam da fruta «'.($fruta?->nome ?? '—').'» antes de gerar o arquivo de vendas.',
                ]);
            }

            $precoVenda = $linha->preco_venda;
            if ($precoVenda === null || (float) $precoVenda <= 0) {
                throw ValidationException::withMessages([
                    'preco_venda' => 'Informe o preço da fruta «'.($fruta->nome ?? '—').'» na captação antes de gerar o arquivo de vendas Cigam.',
                ]);
            }

            $linhasI[] = $this->montarRegistroItem(
                codigoMaterial: $this->campos->codigoMaterialCigam($idCigam),
                descricao: $this->texto((string) ($fruta->nome ?? ''), 200),
                quantidadeUm: $qtdUm,
                precoUnitario: (float) $precoVenda,
                codigoUnidadeNegocio: $codigoUnidadeOrigem,
            );
        }

        if ($linhasI === []) {
            return null;
        }

        $linhaN = $this->montarRegistroNota(
            dataEmissao: $dataEmissao,
            dataEntrada: $dataEntrada,
            serieNotaFiscal: $serieNumero['serie'],
            numeroNotaFiscal: $serieNumero['numero'],
            codigoClienteCobranca: $codigoCliente,
            nomeDestino: $nomeCliente,
            uf: $uf,
            cnpj: $cnpj,
            tipoPessoa: $tipoPessoa,
            numeroDivisao: $this->campos->numeroDivisaoCigam($cliente),
            unidadeNegocio: $codigoUnidadeOrigem,
            centroArmazenagem: $codigoCentroArmazenagem,
            quantidadeItens: count($linhasI),
        );

        return array_merge([$linhaN], $linhasI);
    }

    private function validarClienteLoja(Cliente $cliente): void
    {
        if (trim((string) $cliente->id_cigam) === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'A loja «'.($cliente->fantasia ?: $cliente->razao_social).'» precisa de ID Cigam.',
            ]);
        }
    }

    private function codigoClienteLojaCigam(Cliente $cliente): string
    {
        $digitos = preg_replace('/\D/', '', (string) $cliente->id_cigam) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam da loja.',
            ]);
        }

        return str_pad(substr($digitos, -6), 6, '0', STR_PAD_LEFT);
    }

    private function montarRegistroNota(
        string $dataEmissao,
        string $dataEntrada,
        string $serieNotaFiscal,
        string $numeroNotaFiscal,
        string $codigoClienteCobranca,
        string $nomeDestino,
        string $uf,
        string $cnpj,
        string $tipoPessoa,
        string $numeroDivisao,
        string $unidadeNegocio,
        string $centroArmazenagem,
        int $quantidadeItens,
    ): string {
        $linha = new CiganEdiLinha(self::COMPRIMENTO_LINHA_N);

        $linha
            ->colocar(1, 1, 'N')
            ->colocarExato(3, 7, $serieNotaFiscal)
            ->colocarExato(9, 15, $numeroNotaFiscal)
            ->colocarExato(20, 24, $this->tipoOperacaoEmBrancoCigam())
            ->colocar(26, 33, $dataEmissao)
            ->colocar(35, 42, $dataEntrada)
            ->colocar(44, 44, (string) config('captacao_cigan_edi_vendas.via_transporte', 'R'))
            ->colocar(52, 57, $codigoClienteCobranca, true)
            ->colocar(59, 64, $codigoClienteCobranca, true)
            ->colocar(132, 137, $this->codigoTransportadoraCigam(), true)
            ->colocar(266, 266, (string) config('captacao_cigan_edi_vendas.tipo_frete', '1'))
            ->colocar(283, 283, (string) config('captacao_cigan_edi_vendas.entrada_saida', 'S'))
            ->colocar(301, 314, '0', true)
            ->colocarExato(316, 318, $this->campos->condicaoPagamentoCigam())
            ->colocar(322, 381, $nomeDestino)
            ->colocarExato(383, 412, $this->campoEmBranco(30))
            ->colocarExato(414, 433, $this->campoEmBranco(20))
            ->colocarExato(456, 495, $this->campoEmBranco(40))
            ->colocarExato(497, 516, $this->campoEmBranco(20))
            ->colocarExato(518, 547, $this->campoEmBranco(30))
            ->colocar(549, 550, $uf)
            ->colocarExato(552, 559, $this->campoEmBranco(8))
            ->colocar(561, 574, $cnpj)
            ->colocarExato(576, 595, $this->campoEmBranco(20))
            ->colocar(597, 597, $tipoPessoa)
            ->colocar(599, 600, $numeroDivisao)
            ->colocar(602, 604, $unidadeNegocio)
            ->colocarExato(605, 607, $centroArmazenagem)
            ->colocarExato(608, 608, $this->especieEstoqueCigam())
            ->colocar(683, 687, (string) $quantidadeItens, true);

        return $linha->linha();
    }

    private function montarRegistroItem(
        string $codigoMaterial,
        string $descricao,
        float $quantidadeUm,
        float $precoUnitario,
        string $codigoUnidadeNegocio,
    ): string {
        $linha = new CiganEdiLinha(self::COMPRIMENTO_LINHA_I);

        $linha
            ->colocar(1, 1, 'I')
            ->colocarExato(3, 22, $codigoMaterial)
            ->colocar(24, 38, $this->campos->formatarQuantidadeUmCigam($quantidadeUm), true)
            ->colocarExato(39, 39, ' ')
            ->colocarExato(40, 53, $this->campos->pecasEmBrancoCigam())
            ->colocarExato(56, 70, $this->campos->formatarPrecoUnitarioCigam($precoUnitario))
            ->colocar(95, 95, '0')
            ->colocar(115, 314, $descricao)
            ->colocarExato(372, 376, $this->tipoOperacaoEmBrancoCigam())
            ->colocar(656, 658, $codigoUnidadeNegocio, true)
            ->colocarExato(659, 678, $this->campoEmBranco(20))
            ->colocarExato(679, 679, $this->especieEstoqueCigam())
            ->colocarExato(681, 685, $this->campos->sequenciaItemEmBrancoCigam());

        return $linha->linha();
    }

    /**
     * Campo 004 (N, pos. 20–24) / 018 (I, pos. 372–376) — tipo de operação: em branco nas vendas.
     *
     * @see docs/decisions/ADR-0126-arquivo-cigan-edi-vendas-faturamento.md
     */
    private function tipoOperacaoEmBrancoCigam(): string
    {
        return str_repeat(' ', 5);
    }

    private function codigoTransportadoraCigam(): string
    {
        $codigo = preg_replace('/\D/', '', (string) config('captacao_cigan_edi_vendas.transportadora', '000488')) ?? '';

        return str_pad(substr($codigo !== '' ? $codigo : '000488', -6), 6, '0', STR_PAD_LEFT);
    }

    private function especieEstoqueCigam(): string
    {
        return (string) config('captacao_cigan_edi_vendas.especie_estoque', 'S');
    }

    private function campoEmBranco(int $tamanho): string
    {
        return str_repeat(' ', $tamanho);
    }

    private function documentoCigam(string $documento): string
    {
        $digitos = preg_replace('/\D/', '', $documento) ?? '';

        return str_pad(substr($digitos, 0, 14), 14, ' ', STR_PAD_RIGHT);
    }

    private function texto(string $valor, int $max): string
    {
        $valor = preg_replace('/\s+/', ' ', trim($valor)) ?? '';
        $valor = str_replace(["\r", "\n", "\t"], ' ', $valor);

        return mb_substr($valor, 0, $max, 'UTF-8');
    }
}
