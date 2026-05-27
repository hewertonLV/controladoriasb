<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Support\Captacao\Cigan\CiganEdiEncoding;
use App\Support\Captacao\Cigan\CiganEdiLinha;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Gera TXT EDI NF Cigam (registros N + I) para transferência HUB → unidade de faturamento da carteira.
 *
 * @see docs/decisions/ADR-0105-arquivo-cigan-edi-transferencia-hub.md
 */
final class CiganEdiNfTransferenciaGerador
{
    private const COMPRIMENTO_LINHA_N = 688;

    private const COMPRIMENTO_LINHA_I = 719;

    public function __construct(
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
    ) {}

    public function gerar(CaptacaoLote $lote): string
    {
        $lote = CaptacaoLote::query()->findOrFail($lote->id);
        $lote->unsetRelation('pedidos');
        $lote->loadMissing([
            'unidadeFaturamento.clientePrincipal.unidadeNegocio.estado',
            'unidadeHubOrigem',
        ]);

        $hub = $lote->unidadeHubOrigem;
        $faturamento = $lote->unidadeFaturamento;
        $clienteFaturamento = $this->clienteEmpresaCigam($faturamento);

        if ($hub === null) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_hub_origem' => 'Informe a unidade HUB de origem antes de baixar o arquivo Cigan.',
            ]);
        }

        if (! $hub->is_hub) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_hub_origem' => 'A unidade selecionada não é um HUB.',
            ]);
        }

        $linhasRomaneio = $this->romaneioAbastecimento->preview($lote)
            ->filter(fn (array $linha): bool => (float) $linha['a_receber_um'] > 0);

        if ($linhasRomaneio->isEmpty()) {
            throw ValidationException::withMessages([
                'romaneio' => 'Não há quantidades «a receber» no Romaneio 2 para gerar o arquivo.',
            ]);
        }

        $dataEmissao = $this->dataEmissaoCigam();
        $dataEntrada = $this->dataEntradaCigam();
        $codigoClienteCobranca = $this->codigoClienteCobrancaCigam($faturamento);
        $codigoUnidadeNegocio = $this->codigoUnidadeNegocioCigam(
            (string) $hub->id_cigam,
            'unidade HUB de origem',
        );
        $codigoCentroArmazenagem = $this->codigoCentroArmazenagemCigam($hub);
        $uf = $this->ufClienteCigam($clienteFaturamento);
        $cnpj = $this->documentoCigam((string) $clienteFaturamento->cnpj_cpf);
        $tipoPessoa = strlen(preg_replace('/\D/', '', $cnpj) ?? '') === 11 ? 'F' : 'J';
        $nomeCliente = $this->texto($clienteFaturamento->razao_social ?: $clienteFaturamento->fantasia ?: '', 60);

        $serieNumero = $this->serieENumeroNotaFiscalCigam((string) $hub->id_cigam);

        $linhaN = $this->montarRegistroNota(
            dataEmissao: $dataEmissao,
            dataEntrada: $dataEntrada,
            serieNotaFiscal: $serieNumero['serie'],
            numeroNotaFiscal: $serieNumero['numero'],
            codigoClienteCobranca: $codigoClienteCobranca,
            nomeDestino: $nomeCliente,
            uf: $uf,
            cnpj: $cnpj,
            tipoPessoa: $tipoPessoa,
            numeroDivisao: $this->numeroDivisaoCigam($clienteFaturamento),
            unidadeNegocio: $codigoUnidadeNegocio,
            centroArmazenagem: $codigoCentroArmazenagem,
            quantidadeItens: $linhasRomaneio->count(),
        );

        $linhasI = [];

        foreach ($linhasRomaneio as $linhaRomaneio) {
            $idCigam = trim((string) ($linhaRomaneio['id_cigam'] ?? ''));
            if ($idCigam === '') {
                throw ValidationException::withMessages([
                    'id_cigam' => 'Cadastre o ID Cigam da fruta «'.($linhaRomaneio['fruta_nome'] ?? '—').'» antes de gerar o arquivo Cigan.',
                ]);
            }

            $linhasI[] = $this->montarRegistroItem(
                codigoMaterial: $this->codigoMaterialCigam($idCigam),
                descricao: $this->texto((string) ($linhaRomaneio['fruta_nome'] ?? ''), 200),
                quantidadeUm: (float) $linhaRomaneio['a_receber_um'],
                codigoUnidadeNegocio: $codigoUnidadeNegocio,
            );
        }

        if ($linhasI === []) {
            throw ValidationException::withMessages([
                'romaneio' => 'Nenhum item válido para o arquivo Cigan.',
            ]);
        }

        return $linhaN."\n".implode("\n", $linhasI)."\n";
    }

    /**
     * Cigam espera arquivo ANSI/ISO-8859-1; translitera caracteres fora da página Latin-1.
     */
    public function paraIso88591(string $conteudoUtf8): string
    {
        return CiganEdiEncoding::paraIso88591($conteudoUtf8);
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
            ->colocarExato(20, 24, $this->tipoOperacaoCigam())
            ->colocar(26, 33, $dataEmissao)
            ->colocar(35, 42, $dataEntrada)
            ->colocar(44, 44, (string) config('captacao_cigan_edi.via_transporte', 'R'))
            ->colocar(52, 57, $codigoClienteCobranca, true)
            ->colocar(59, 64, $codigoClienteCobranca, true)
            ->colocar(132, 137, $this->codigoTransportadoraCigam(), true)
            ->colocar(266, 266, (string) config('captacao_cigan_edi.tipo_frete', '1'))
            ->colocar(283, 283, (string) config('captacao_cigan_edi.entrada_saida', 'S'))
            ->colocar(301, 314, '0', true)
            ->colocarExato(316, 318, $this->condicaoPagamentoCigam())
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
        string $codigoUnidadeNegocio,
    ): string {
        $linha = new CiganEdiLinha(self::COMPRIMENTO_LINHA_I);

        $qtdFormatada = $this->formatarQuantidadeCigam($quantidadeUm);

        $linha
            ->colocar(1, 1, 'I')
            ->colocarExato(3, 22, $codigoMaterial)
            ->colocar(24, 38, $qtdFormatada, true)
            ->colocarExato(39, 39, ' ')
            ->colocarExato(40, 53, $this->pecasEmBrancoCigam())
            ->colocarExato(56, 70, $this->precoUnitarioEmBrancoCigam())
            ->colocar(95, 95, '0')
            ->colocar(115, 314, $descricao)
            ->colocarExato(372, 376, $this->tipoOperacaoCigam())
            ->colocar(656, 658, $codigoUnidadeNegocio, true)
            ->colocarExato(659, 678, $this->campoEmBranco(20))
            ->colocarExato(679, 679, $this->especieEstoqueCigam())
            ->colocarExato(681, 685, $this->sequenciaItemEmBrancoCigam());

        return $linha->linha();
    }

    /**
     * Série (pos. 3–7) + número NF (pos. 9–15) = «NF» + id_cigam da UN sem zeros à esquerda.
     * Cabe nos 5 primeiros caracteres; o que passar de 5 vai para o campo 003 (7 pos.).
     *
     * @return array{serie: string, numero: string}
     *
     * @see docs/decisions/ADR-0115-cigan-edi-numero-nf-unidade-negocio.md
     */
    public function serieENumeroNotaFiscalCigam(string $idCigamUnidade): array
    {
        $digitos = preg_replace('/\D/', '', $idCigamUnidade) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam da unidade de negócio (HUB) para montar a série da NF no arquivo Cigan.',
            ]);
        }

        $semZeros = ltrim($digitos, '0') ?: '0';
        $valor = 'NF'.$semZeros;

        return [
            'serie' => str_pad(substr($valor, 0, 5), 5, ' ', STR_PAD_RIGHT),
            'numero' => str_pad(substr($valor, 5, 7), 7, ' ', STR_PAD_RIGHT),
        ];
    }

    /**
     * Cliente principal da unidade de faturamento ([ADR-0106](../decisions/ADR-0106-unidade-negocio-codigo-cliente.md)).
     */
    public function clienteEmpresaCigam(UnidadeNegocio $faturamento): Cliente
    {
        $cliente = $faturamento->clientePrincipal;

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'id_cliente' => 'Cadastre o código do cliente na unidade de faturamento «'.$faturamento->nome.'» antes de gerar o arquivo Cigan.',
            ]);
        }

        if (trim((string) $cliente->id_cigam) === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'O cliente vinculado à unidade de faturamento precisa de ID Cigam.',
            ]);
        }

        return $cliente;
    }

    /**
     * Campos 009/010 — Cliente e cobrança (pos. 52–57 e 59–64): id_cigam do cliente da UN.
     */
    public function codigoClienteCobrancaCigam(UnidadeNegocio $faturamento): string
    {
        $cliente = $this->clienteEmpresaCigam($faturamento);

        return $this->codigoEmpresaCigam($cliente->id_cigam, 'cliente vinculado à unidade de faturamento');
    }

    /**
     * Campo divisão (pos. 599–600 registro N): número do cadastro do cliente ([ADR-0108]).
     */
    public function numeroDivisaoCigam(Cliente $cliente): string
    {
        $digitos = preg_replace('/\D/', '', (string) ($cliente->numero_divisao ?? '')) ?? '';
        if ($digitos === '') {
            $digitos = preg_replace('/\D/', '', (string) config('captacao_cigan_edi.divisao', '10')) ?? '10';
        }

        return str_pad(substr($digitos, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Campo 005 — Data emissão (pos. 26–33, DDMMAAAA): sempre o dia do download/geração.
     */
    public function dataEmissaoCigam(?Carbon $agora = null): string
    {
        return ($agora ?? now())->format('dmY');
    }

    /**
     * Campo 006 — Data entrada (pos. 35–42, DDMMAAAA): dia atual (igual à emissão).
     */
    public function dataEntradaCigam(?Carbon $agora = null): string
    {
        return $this->dataEmissaoCigam($agora);
    }

    /**
     * Campo 004 (N) / 018 (I) — Tipo de operação (pos. 20–24 e 372–376), ex.: 5152A (transferência).
     *
     * @see docs/decisions/ADR-0117-cigan-edi-tipo-operacao-5152a.md
     */
    public function tipoOperacaoCigam(): string
    {
        $valor = strtoupper(trim((string) config('captacao_cigan_edi.tipo_operacao', '5152A')));

        return str_pad(substr($valor !== '' ? $valor : '5152A', 0, 5), 5, ' ', STR_PAD_RIGHT);
    }

    /**
     * Campo 018 — Transportadora (pos. 132–137): código fixo Cigam.
     */
    public function codigoTransportadoraCigam(): string
    {
        $codigo = preg_replace('/\D/', '', (string) config('captacao_cigan_edi.transportadora', '000488')) ?? '';

        return str_pad(substr($codigo !== '' ? $codigo : '000488', -6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Campo 037 — Condição de pagamento (pos. 316–318): em branco.
     */
    public function condicaoPagamentoCigam(): string
    {
        return str_repeat(' ', 3);
    }

    /**
     * Campo 045 — UF (pos. 549–550): estado da unidade de negócio vinculada ao cliente.
     */
    public function ufClienteCigam(Cliente $cliente): string
    {
        $cliente->loadMissing('unidadeNegocio.estado');
        $uf = strtoupper(trim((string) ($cliente->unidadeNegocio?->estado?->abreviacao ?? '')));

        if (strlen($uf) !== 2) {
            throw ValidationException::withMessages([
                'uf' => 'Cadastre o estado (UF) na unidade de negócio do cliente antes de gerar o arquivo Cigan.',
            ]);
        }

        return $uf;
    }

    private function campoEmBranco(int $tamanho): string
    {
        return str_repeat(' ', $tamanho);
    }

    /**
     * Campo 004 — Peças (pos. 40–53, N8.6): em branco; quantidade vai só no campo 003 (UM).
     */
    public function pecasEmBrancoCigam(): string
    {
        return str_repeat(' ', 14);
    }

    /**
     * Campo 005 — Preço unitário (pos. 56–70, N10.5): em branco; Cigan calcula na importação.
     */
    public function precoUnitarioEmBrancoCigam(): string
    {
        return str_repeat(' ', 15);
    }

    /**
     * Campo 042 — Sequência item (pos. 681–685): em branco; Cigan numera na importação.
     */
    public function sequenciaItemEmBrancoCigam(): string
    {
        return str_repeat(' ', 5);
    }

    /**
     * Campo 053 (N, pos. 608) / 042 (I, pos. 679) — Espécie estoque: sempre «S» (saída).
     * Centro armazenagem: 605–607 (N) e 659–661 (I); separador 662–678 no I.
     */
    public function especieEstoqueCigam(): string
    {
        return (string) config('captacao_cigan_edi.especie_estoque', 'S');
    }

    /**
     * Campo 002 — Série (pos. 3–7, máscara UUUUU).
     * Manual: usar 3 posições com 2 espaços à esquerda (ex.: série 1 → "  001").
     */
    public function serieNotaFiscal(): string
    {
        $serie = preg_replace('/\D/', '', (string) config('captacao_cigan_edi.serie', '1')) ?? '';
        $serie = $serie !== '' ? $serie : '1';

        return '  '.str_pad(substr($serie, -3), 3, '0', STR_PAD_LEFT);
    }

    private function codigoEmpresaCigam(string $idCigam, string $rotulo): string
    {
        $digitos = preg_replace('/\D/', '', $idCigam) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => "Cadastre o ID Cigam da {$rotulo}.",
            ]);
        }

        return str_pad(substr($digitos, -6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Campo 002 — Código material (pos. 3–22): 14 espaços + 6 dígitos do final do id_cigam da fruta.
     */
    public function codigoMaterialCigam(string $idCigam): string
    {
        $digitos = preg_replace('/\D/', '', $idCigam) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam do material (fruta).',
            ]);
        }

        return str_repeat(' ', 14).str_pad(substr($digitos, -6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Campo 052 — Centro armazenagem (pos. 605–607, só registro N): cadastro da UN HUB (ex.: 001).
     * Usar `colocarExato` — o Cigan valida os 3 caracteres; valor «050» nas pos. 606–607 vira «50» no ERP.
     *
     * @see docs/decisions/ADR-0116-cigan-edi-centro-armazenagem-hub.md
     */
    public function codigoCentroArmazenagemCigam(UnidadeNegocio $unidade): string
    {
        $digitos = preg_replace('/\D/', '', (string) ($unidade->centro_armazenagem ?? '')) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'centro_armazenagem' => 'Cadastre o centro de armazenagem da unidade HUB «'.$unidade->nome.'» antes de gerar o arquivo Cigan.',
            ]);
        }

        $normalizado = str_pad(substr($digitos, -3), 3, '0', STR_PAD_LEFT);

        if ($normalizado === '050') {
            throw ValidationException::withMessages([
                'centro_armazenagem' => 'O centro de armazenagem «050» não é válido no Cigan para esta operação. Cadastre «001» (ou o centro existente no ERP) na unidade HUB «'.$unidade->nome.'».',
            ]);
        }

        return $normalizado;
    }

    /**
     * Campo 051 — Unidade negócio (pos. 602–604 no N; 656–658 no I): últimos 3 dígitos do id_cigam da UN.
     */
    public function codigoUnidadeNegocioCigam(string $idCigam, string $rotulo): string
    {
        $digitos = preg_replace('/\D/', '', $idCigam) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => "Cadastre o ID Cigam da {$rotulo}.",
            ]);
        }

        return str_pad(substr($digitos, -3), 3, '0', STR_PAD_LEFT);
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

    /**
     * Campo 003 — Quantidade (pos. 24–38, máscara N8.6): 15 dígitos = valor × 1.000.000.
     *
     * @see docs/decisions/ADR-0113-cigan-edi-quantidade-sem-n86.md
     */
    private function formatarQuantidadeCigam(float $quantidade): string
    {
        return $this->formatarQuantidadeN86($quantidade);
    }

    /** Máscara N8.6 — 15 posições (pos. 24–38 registro I). */
    public function formatarQuantidadeN86(float $quantidade): string
    {
        $escalado = (int) round($quantidade * 1_000_000);

        return str_pad((string) max(0, $escalado), 15, '0', STR_PAD_LEFT);
    }

    /** Máscara N10.5 — 15 posições (pos. 56–70 registro I). */
    public function formatarPrecoUnitarioCigam(float $preco): string
    {
        return $this->formatarPrecoN105($preco);
    }

    /** Campo 003 — Quantidade UM (pos. 24–38 registro I), máscara N8.6. */
    public function formatarQuantidadeUmCigam(float $quantidade): string
    {
        return $this->formatarQuantidadeN86($quantidade);
    }

    /** Máscara N10.5 — 15 posições. */
    private function formatarPrecoN105(float $preco): string
    {
        $escalado = (int) round($preco * 100_000);

        return str_pad((string) max(0, $escalado), 15, '0', STR_PAD_LEFT);
    }
}
