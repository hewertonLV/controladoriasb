<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteCiganExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class GerarArquivoCiganService
{
    public function gerarTransferencia(CaptacaoLote $lote, User $user): CaptacaoLoteCiganExport
    {
        $conteudo = $lote->tipo === CaptacaoLoteTipo::RomaneioManual
            ? $this->conteudoTransferenciaRomaneioManual($lote)
            : $this->conteudoTransferenciaCaptacao($lote);

        return $this->gerar($lote, $user, 'TRANSFERENCIA', $conteudo);
    }

    public function gerarVendas(CaptacaoLote $lote, User $user): CaptacaoLoteCiganExport
    {
        return $this->gerar($lote, $user, 'VENDA', $this->conteudoPlaceholderVendas($lote));
    }

    /**
     * Conteúdo TXT EDI NF Cigam (transferência HUB → galpão, Romaneio 2 «a receber»).
     */
    public function conteudoTxtTransferencia(CaptacaoLote $lote): string
    {
        $gerador = app(CiganEdiNfTransferenciaGerador::class);

        return $gerador->paraIso88591($gerador->gerar($lote));
    }

    /**
     * Conteúdo TXT EDI NF Cigam (vendas faturamento → loja).
     */
    public function conteudoTxtVendas(CaptacaoLote $lote): string
    {
        $gerador = app(CiganEdiNfVendaGerador::class);

        return $gerador->paraIso88591($gerador->gerar($lote));
    }

    private function gerar(CaptacaoLote $lote, User $user, string $tipo, string $conteudo): CaptacaoLoteCiganExport
    {
        $path = sprintf(
            'captacao/cigan/lote-%d-%s-%s.csv',
            $lote->id,
            strtolower($tipo),
            now()->format('YmdHis'),
        );

        Storage::disk('local')->put($path, $conteudo);

        return CaptacaoLoteCiganExport::query()->create([
            'id_captacao_lote' => $lote->id,
            'tipo' => $tipo,
            'versao_layout' => 'v1',
            'caminho_arquivo' => $path,
            'user_id' => $user->id,
        ]);
    }

    private function conteudoTransferenciaCaptacao(CaptacaoLote $lote): string
    {
        $lote->load(['pedidos.itens.fruta', 'unidadeGalpao', 'unidadeFaturamento']);

        $linhas = ['tipo;lote;unidade_faturamento;galpao_destino;fruta;quantidade'];

        foreach ($lote->pedidos as $pedido) {
            foreach ($pedido->itens as $item) {
                $linhas[] = implode(';', [
                    'TRANSFERENCIA',
                    (string) $lote->id,
                    $this->escaparCsv($lote->unidadeFaturamento->nome),
                    $this->escaparCsv($lote->unidadeGalpao->nome),
                    $this->escaparCsv($item->fruta->nome),
                    (string) $item->quantidade,
                ]);
            }
        }

        return implode("\n", $linhas)."\n";
    }

    private function conteudoTransferenciaRomaneioManual(CaptacaoLote $lote): string
    {
        $lote->load([
            'unidadeGalpao:id,nome',
            'unidadeFaturamento:id,nome',
            'manualLinhas.fruta:id,nome',
            'manualLinhas.unidadeOrigemFisica:id,nome',
        ]);

        $linhas = ['tipo;lote;unidade_faturamento;galpao_destino;fruta;quantidade_um;origem_fisica;motivo'];

        foreach ($lote->manualLinhas as $linha) {
            $linhas[] = implode(';', [
                'TRANSFERENCIA_MANUAL',
                (string) $lote->id,
                $this->escaparCsv($lote->unidadeFaturamento->nome),
                $this->escaparCsv($lote->unidadeGalpao->nome),
                $this->escaparCsv($linha->fruta->nome),
                (string) $linha->quantidade,
                $this->escaparCsv($linha->unidadeOrigemFisica->nome),
                $this->escaparCsv($linha->motivo ?? ''),
            ]);
        }

        return implode("\n", $linhas)."\n";
    }

    private function conteudoPlaceholderVendas(CaptacaoLote $lote): string
    {
        $lote->load(['pedidos.cliente', 'pedidos.itens.fruta', 'unidadeFaturamento']);

        $linhas = ['tipo;lote;unidade_faturamento;cliente;fruta;quantidade;preco'];

        foreach ($lote->pedidos as $pedido) {
            foreach ($pedido->itens as $item) {
                $linhas[] = implode(';', [
                    'VENDA',
                    (string) $lote->id,
                    $this->escaparCsv($lote->unidadeFaturamento->nome),
                    $this->escaparCsv($pedido->cliente->razao_social),
                    $this->escaparCsv($item->fruta->nome),
                    (string) $item->quantidade,
                    (string) ($item->preco_venda ?? '0'),
                ]);
            }
        }

        return implode("\n", $linhas)."\n";
    }

    private function escaparCsv(string $valor): string
    {
        if (str_contains($valor, ';') || str_contains($valor, '"') || str_contains($valor, "\n")) {
            return '"'.str_replace('"', '""', $valor).'"';
        }

        return $valor;
    }
}
