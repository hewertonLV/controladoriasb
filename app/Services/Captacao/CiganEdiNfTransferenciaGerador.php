<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\Captacao\Cigan\CiganEdiLinha;
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
        $lote->loadMissing([
            'unidadeFaturamento.estado',
            'unidadeHubOrigem',
        ]);

        $hub = $lote->unidadeHubOrigem;
        $faturamento = $lote->unidadeFaturamento;

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

        $frutas = Fruta::query()
            ->whereIn('id', $linhasRomaneio->pluck('id_fruta'))
            ->get()
            ->keyBy('id');

        $data = $lote->data_referencia->format('dmY');
        $codigoFaturamento = $this->codigoEmpresaCigam(
            $faturamento->id_cigam,
            'unidade de faturamento da carteira (destino)',
        );
        $codigoHub = $this->codigoEmpresaCigam($hub->id_cigam, 'HUB de origem');
        $uf = strtoupper((string) ($faturamento->estado?->abreviacao ?? 'RS'));
        $cnpj = $this->documentoCigam((string) $faturamento->cpf_cnpj);
        $tipoPessoa = strlen(preg_replace('/\D/', '', $cnpj) ?? '') === 11 ? 'F' : 'J';
        $nomeFaturamento = $this->texto($faturamento->razao_social ?: $faturamento->nome, 60);

        $linhaN = $this->montarRegistroNota(
            lote: $lote,
            data: $data,
            codigoDestino: $codigoFaturamento,
            codigoHub: $codigoHub,
            nomeDestino: $nomeFaturamento,
            uf: $uf,
            cnpj: $cnpj,
            tipoPessoa: $tipoPessoa,
            unidadeNegocio: $this->codigoUnidadeNegocio($faturamento->id_cigam),
            quantidadeItens: $linhasRomaneio->count(),
        );

        $linhasI = [];
        $sequencia = 1;

        foreach ($linhasRomaneio as $linhaRomaneio) {
            $fruta = $frutas->get($linhaRomaneio['id_fruta']);
            if ($fruta === null) {
                continue;
            }

            $linhasI[] = $this->montarRegistroItem(
                fruta: $fruta,
                quantidadeUm: (float) $linhaRomaneio['a_receber_um'],
                sequencia: $sequencia++,
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
        $convertido = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $conteudoUtf8);

        return $convertido !== false ? $convertido : mb_convert_encoding($conteudoUtf8, 'ISO-8859-1', 'UTF-8');
    }

    private function montarRegistroNota(
        CaptacaoLote $lote,
        string $data,
        string $codigoDestino,
        string $codigoHub,
        string $nomeDestino,
        string $uf,
        string $cnpj,
        string $tipoPessoa,
        string $unidadeNegocio,
        int $quantidadeItens,
    ): string {
        $linha = new CiganEdiLinha(self::COMPRIMENTO_LINHA_N);

        $numeroNf = str_pad((string) $lote->id, 10, '0', STR_PAD_LEFT);

        $linha
            ->colocar(1, 1, 'N')
            ->colocar(3, 7, (string) config('captacao_cigan_edi.serie', '1'))
            ->colocar(9, 18, $numeroNf, true)
            ->colocar(20, 24, (string) config('captacao_cigan_edi.tipo_operacao', '51101'))
            ->colocar(26, 33, $data)
            ->colocar(35, 42, $data)
            ->colocar(44, 44, (string) config('captacao_cigan_edi.via_transporte', 'R'))
            ->colocar(52, 57, $codigoDestino, true)
            ->colocar(59, 64, $codigoDestino, true)
            ->colocar(132, 137, $codigoHub, true)
            ->colocar(266, 266, (string) config('captacao_cigan_edi.tipo_frete', '1'))
            ->colocar(283, 283, (string) config('captacao_cigan_edi.entrada_saida', 'E'))
            ->colocar(301, 314, '0', true)
            ->colocar(316, 318, (string) config('captacao_cigan_edi.condicao_pagamento', '001'))
            ->colocar(322, 381, $nomeDestino)
            ->colocar(383, 412, 'SB CONTROLADORIA')
            ->colocar(414, 433, '0000000000')
            ->colocar(456, 495, $nomeDestino)
            ->colocar(497, 516, 'NAO INFORMADO')
            ->colocar(518, 547, $nomeDestino)
            ->colocar(549, 550, $uf)
            ->colocar(552, 559, '00000000', true)
            ->colocar(561, 574, $cnpj)
            ->colocar(576, 595, 'ISENTO')
            ->colocar(597, 597, $tipoPessoa)
            ->colocar(599, 600, (string) config('captacao_cigan_edi.divisao', '10'))
            ->colocar(602, 604, $unidadeNegocio)
            ->colocar(683, 687, (string) $quantidadeItens, true);

        return $linha->linha();
    }

    private function montarRegistroItem(Fruta $fruta, float $quantidadeUm, int $sequencia): string
    {
        $linha = new CiganEdiLinha(self::COMPRIMENTO_LINHA_I);

        $codigoMaterial = $this->codigoMaterialCigam($fruta->id_cigam);
        $qtdFormatada = $this->formatarQuantidadeN86($quantidadeUm);
        $precoUnitario = $this->formatarPrecoN105(0.00001);
        $descricao = $this->texto($fruta->nome, 200);

        $linha
            ->colocar(1, 1, 'I')
            ->colocar(3, 22, $codigoMaterial)
            ->colocar(24, 38, $qtdFormatada, true)
            ->colocar(40, 53, $qtdFormatada, true)
            ->colocar(56, 70, $precoUnitario, true)
            ->colocar(95, 95, '0')
            ->colocar(115, 314, $descricao)
            ->colocar(372, 376, (string) config('captacao_cigan_edi.tipo_operacao', '51101'))
            ->colocar(656, 658, $this->codigoUnidadeNegocio($fruta->id_cigam))
            ->colocar(681, 685, (string) $sequencia, true);

        return $linha->linha();
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

    private function codigoMaterialCigam(string $idCigam): string
    {
        $digitos = preg_replace('/\D/', '', $idCigam) ?? '';
        if ($digitos === '') {
            throw ValidationException::withMessages([
                'id_cigam' => 'Cadastre o ID Cigam do material (fruta).',
            ]);
        }

        return str_pad(substr($digitos, -20), 20, '0', STR_PAD_LEFT);
    }

    private function codigoUnidadeNegocio(string $idCigam): string
    {
        $digitos = preg_replace('/\D/', '', $idCigam) ?? '';

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

        return mb_substr($valor, 0, $max, 'UTF-8');
    }

    /** Máscara N8.6 — 15 posições (ex.: 7,000000 → 000000007000000). */
    private function formatarQuantidadeN86(float $quantidade): string
    {
        $escalado = (int) round($quantidade * 1_000_000);

        return str_pad((string) max(0, $escalado), 15, '0', STR_PAD_LEFT);
    }

    /** Máscara N10.5 — 15 posições. */
    private function formatarPrecoN105(float $preco): string
    {
        $escalado = (int) round($preco * 100_000);

        return str_pad((string) max(0, $escalado), 15, '0', STR_PAD_LEFT);
    }
}
